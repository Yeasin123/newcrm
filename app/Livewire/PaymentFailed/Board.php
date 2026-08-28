<?php

namespace App\Livewire\PaymentFailed;

use App\Models\Client;
use App\Models\ClientStatusOption;
use App\Services\Viewboard\ViewboardTracker;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Payment Failed call viewboard — walk the queue of customers whose billing
 * has failed (clients.status = 0), one at a time. The comms buttons here log a
 * rep action + a note (the rep dials from the 3CX bar). Templated customer
 * SMS / voice / voicemail is sent from the Failed Payments admin screen, which
 * drives the Phase 9f telephony gateways.
 */
class Board extends Component
{
    #[Url]
    public int $i = 0;

    #[Url]
    public string $salesman = '';

    public string $noteBody = '';

    public string $statusLabel = '';

    protected function queue(): Builder
    {
        return Client::query()
            ->where('status', 0)
            ->when($this->salesman !== '', fn (Builder $q) => $q->where('salesman_name', $this->salesman))
            ->orderByDesc('payment_faild_date')
            ->orderByDesc('id');
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

    public function saveNote(ViewboardTracker $tracker): void
    {
        $this->validateOnly('noteBody', ['noteBody' => 'required|string|min:2|max:5000']);
        $client = $this->currentClient();
        abort_unless($client, 404);

        $tracker->note('failed', $client, $this->noteBody, $this->statusLabel ?: null);
        $this->reset('noteBody');
        Flux::toast(variant: 'success', text: __('Note saved.'));
    }

    /** @param string $action click_to_call|save|send_sms|send_mail|voice */
    public function logAction(ViewboardTracker $tracker, string $action): void
    {
        $client = $this->currentClient();
        abort_unless($client, 404);

        $tracker->action('failed', $client, $action, $this->statusLabel ?: null);
        Flux::toast(variant: 'success', text: __(':action logged.', ['action' => ucwords(str_replace('_', ' ', $action))]));
    }

    public function setStatus(ViewboardTracker $tracker, string $label): void
    {
        $this->statusLabel = $label;
        $client = $this->currentClient();
        if ($client) {
            $tracker->action('failed', $client, 'save', $label);
        }
    }

    /** Mark the customer recovered (status back to active). Dry-run friendly. */
    public function markRecovered(ViewboardTracker $tracker): void
    {
        $client = $this->currentClient();
        abort_unless($client, 404);

        $client->forceFill(['status' => 1, 'payment_faild_date' => null])->save();
        $tracker->note('failed', $client, 'Marked recovered from the Payment Failed board.', 'Recovered');
        Flux::toast(variant: 'success', text: __('Customer marked recovered.'));
    }

    protected function currentClient(): ?Client
    {
        return $this->queue()->offset($this->i)->limit(1)->first();
    }

    public function render(): View
    {
        $total = $this->queue()->toBase()->getCountForPagination();
        $client = $this->currentClient();
        $client?->loadMissing(['propertyPackage:id,title', 'latestFailedPolicyPaymentHistory', 'buttonTracks.user:id,first_name,last_name']);

        return view('livewire.payment-failed.board', [
            'client' => $client,
            'total' => $total,
            'statuses' => ClientStatusOption::orderBy('title')->pluck('title'),
            'salesmen' => Client::query()->where('status', 0)->whereNotNull('salesman_name')
                ->where('salesman_name', '!=', '')->distinct()->orderBy('salesman_name')->pluck('salesman_name'),
        ])->layout('layouts.app', ['title' => 'Payment Failed']);
    }
}
