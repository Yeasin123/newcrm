<?php

namespace App\Livewire\Renewals;

use App\Models\Client;
use App\Models\ClientRenewButtonTrack;
use App\Services\Renewals\RenewalQueueService;
use App\Services\Viewboard\ViewboardTracker;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Renewal Board — walk the renewal queue one customer at a time and log a
 * disposition per call (Renewed / Upgraded / Spoke With / Call Back / No
 * Answer / Not Interested / Skipped). Each disposition writes a
 * client_renew_button_tracks row + a client_renew_track_actions row (via
 * ViewboardTracker) and advances to the next customer.
 *
 * The actual renewal sale is a separate step: "Renew Customer — Sale Sheet"
 * opens the Sale Sheet in a new tab with the customer's current plan
 * pre-filled (?renew=<id>).
 */
class Board extends Component
{
    /** label, track-action column, whether it drops the customer from the queue */
    private const DISPOSITIONS = [
        'renewed' => ['Renewed!', 'convert_to_client', true],
        'upgraded' => ['Upgraded Plan', 'save', false],
        'spoke_with' => ['Spoke With', 'save', false],
        'call_back' => ['Schedule Call Back', 'save', false],
        'no_answer' => ['No Answer', 'click_to_call', false],
        'not_interested' => ['Not Interested', 'save', false],
    ];

    #[Url]
    public int $i = 0;

    #[Url]
    public string $filter = 'yearly'; // yearly | monthly | all

    /** Deep-link target from the Renewal Queue "Board" button. */
    #[Url]
    public ?int $client = null;

    public string $noteBody = '';

    public function mount(RenewalQueueService $queue): void
    {
        if ($this->client) {
            $pos = array_search($this->client, $queue->ids($this->filter), true);

            if ($pos === false) {
                $pos = array_search($this->client, $queue->ids('all'), true);
                if ($pos !== false) {
                    $this->filter = 'all';
                }
            }

            if ($pos !== false) {
                $this->i = (int) $pos;
                $this->client = null;
            }
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['yearly', 'monthly', 'all'], true) ? $filter : 'yearly';
        $this->i = 0;
        $this->reset('noteBody');
    }

    public function next(): void
    {
        $this->i++;
        $this->reset('noteBody');
    }

    public function prev(): void
    {
        $this->i = max(0, $this->i - 1);
        $this->reset('noteBody');
    }

    private function currentClient(RenewalQueueService $queue): ?Client
    {
        $ids = $queue->ids($this->filter);
        $id = $ids[$this->i] ?? $this->client;

        return $id ? Client::find($id) : null;
    }

    public function postNote(RenewalQueueService $queue, ViewboardTracker $tracker): void
    {
        $this->validate(['noteBody' => 'required|string|min:2|max:5000']);
        $client = $this->currentClient($queue);
        abort_unless($client, 404);

        $tracker->note('renewal', $client, $this->noteBody, 'Note');
        $this->reset('noteBody');
        Flux::toast(variant: 'success', text: __('Note posted.'));
    }

    public function disposition(string $key, RenewalQueueService $queue, ViewboardTracker $tracker): void
    {
        abort_unless(isset(self::DISPOSITIONS[$key]), 404);
        [$label, $column, $dropsFromQueue] = self::DISPOSITIONS[$key];

        $client = $this->currentClient($queue);
        abort_unless($client, 404);

        $tracker->action('renewal', $client, $column, $label);
        ClientRenewButtonTrack::create([
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            'create_date' => now()->toDateTimeString(),
            'status' => null,
            'button_text' => $label,
            'comments' => $this->noteBody !== '' ? $this->noteBody : $label,
        ]);

        if ($dropsFromQueue) {
            $client->forceFill(['renewed' => 1])->save();
        }

        $this->reset('noteBody');
        Flux::toast(variant: 'success', text: __(':label logged.', ['label' => $label]));

        if (! $dropsFromQueue) {
            $this->next();
        }
    }

    public function skip(RenewalQueueService $queue, ViewboardTracker $tracker): void
    {
        $client = $this->currentClient($queue);
        if ($client) {
            $tracker->action('renewal', $client, 'save', 'Skipped');
            ClientRenewButtonTrack::create([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'create_date' => now()->toDateTimeString(),
                'status' => null,
                'button_text' => 'Skipped',
                'comments' => 'Skipped',
            ]);
        }

        $this->next();
    }

    public function render(RenewalQueueService $queue): View
    {
        $ids = $queue->ids($this->filter);
        $total = count($ids);

        $client = $this->currentClient($queue);
        $row = $client ? $queue->rowFor($client->id) : null;

        $client?->loadMissing([
            'propertyPackage:id,title',
            'cardProfiles',
            'renewButtonTracks.user:id,first_name,last_name',
        ]);

        $history = ClientRenewButtonTrack::query()
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->when(! $client, fn ($q) => $q->whereRaw('1 = 0'))
            ->with('user:id,first_name,last_name')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $payments = $client
            ? $client->paymentHistories()->where('claim_payment', 0)->orderByDesc('id')->limit(6)->get()
            : collect();

        return view('livewire.renewals.board', [
            'customer' => $client,
            'row' => $row,
            'total' => $total,
            'position' => $client && isset($ids[$this->i]) ? $this->i + 1 : ($client ? 1 : 0),
            'history' => $history,
            'payments' => $payments,
        ])->layout('layouts.app', ['title' => 'Renewal Board']);
    }
}
