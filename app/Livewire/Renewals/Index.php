<?php

namespace App\Livewire\Renewals;

use App\Models\Client;
use App\Models\ClientRenewButtonTrack;
use App\Services\Renewals\RenewalQueueService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Renewal Queue — every active yearly / monthly policy whose coverage ends
 * within ~4 months (see RenewalQueueService). Yearly and monthly contracts
 * sit on separate tabs; "Board" drops the customer into the renewal call
 * board, which walks the very same list.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'yearly'; // yearly | monthly

    #[Url]
    public string $search = '';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['yearly', 'monthly'], true) ? $tab : 'yearly';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function markContacted(int $clientId): void
    {
        $client = Client::find($clientId);
        abort_unless($client, 404);

        ClientRenewButtonTrack::create([
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            'create_date' => now()->toDateTimeString(),
            'status' => null,
            'button_text' => 'Contacted',
            'comments' => 'Marked contacted from the Renewal Queue.',
        ]);

        Flux::toast(variant: 'success', text: __('Marked contacted.'));
    }

    public function startBoard(): void
    {
        $this->redirectRoute('renewals.board', ['filter' => $this->tab], navigate: true);
    }

    public function render(RenewalQueueService $queue): View
    {
        $rows = $queue->window();

        if ($this->search !== '') {
            $term = mb_strtolower(trim($this->search));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->name), $term)
                || str_contains(mb_strtolower((string) $r->policy_number), $term)
                || str_contains(mb_strtolower((string) $r->email), $term)
                || str_contains(mb_strtolower((string) $r->contact), $term))->values();
        }

        $yearly = $rows->where('is_yearly', true)->values();
        $monthly = $rows->where('is_yearly', false)->values();

        $contactedToday = ClientRenewButtonTrack::query()
            ->whereIn('client_id', $rows->pluck('id'))
            ->where('create_date', '>=', Carbon::today()->toDateTimeString())
            ->pluck('client_id')
            ->unique();

        $active = $this->tab === 'monthly' ? $monthly : $yearly;
        $perPage = 25;
        $page = max(1, (int) $this->getPage());
        $paginator = new LengthAwarePaginator(
            $active->forPage($page, $perPage),
            $active->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return view('livewire.renewals.index', [
            'rows' => $paginator,
            'contactedIds' => $contactedToday,
            'kpi' => [
                'total' => $rows->count(),
                'yearly' => $yearly->count(),
                'monthly' => $monthly->count(),
                'critical' => $rows->where('days_left', '<=', 7)->count(),
                'contacted' => $contactedToday->count(),
                'yearly_value' => $rows->sum('annual'),
            ],
        ])->layout('layouts.app', ['title' => 'Renewals']);
    }
}
