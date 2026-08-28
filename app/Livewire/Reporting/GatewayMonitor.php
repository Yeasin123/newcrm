<?php

namespace App\Livewire\Reporting;

use App\Models\AnetWebhookEvent;
use App\Models\AuthorizeAccount;
use App\Services\Reporting\LogScanner;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gateway Monitor. Ported from GatewayMonitorController — browse the
 * anet_webhook_events store with filters + an event-type breakdown, and scan
 * laravel.log for Authorize.Net error / retry lines. (The legacy
 * manual_payment_debug.txt trace reader is dropped — that file is a legacy
 * debug artefact.)
 */
class GatewayMonitor extends Component
{
    use WithPagination;

    #[Url]
    public ?int $authorize_account_id = null;

    #[Url]
    public string $event_type = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $date_from = '';

    #[Url]
    public string $date_to = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('Settings'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['authorize_account_id', 'event_type', 'search', 'date_from', 'date_to'], true)) {
            $this->resetPage();
        }
    }

    protected function query(): Builder
    {
        return AnetWebhookEvent::query()
            ->with('authorizeAccount:id,name,environment,is_active')
            ->when($this->authorize_account_id, fn (Builder $q) => $q->where('authorize_account_id', $this->authorize_account_id))
            ->when($this->event_type !== '', fn (Builder $q) => $q->where('event_type', 'like', '%'.$this->event_type.'%'))
            ->when($this->search !== '', function (Builder $q) {
                $t = '%'.$this->search.'%';
                $q->where(fn (Builder $i) => $i->where('event_id', 'like', $t)
                    ->orWhere('event_type', 'like', $t)
                    ->orWhere('payload_id', 'like', $t)
                    ->orWhere('raw', 'like', $t));
            })
            ->when($this->date_from !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', Carbon::parse($this->date_from)->toDateString()))
            ->when($this->date_to !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', Carbon::parse($this->date_to)->toDateString()));
    }

    public function render(LogScanner $logs): View
    {
        $logEntries = $logs->anetEntries();

        return view('livewire.reporting.gateway-monitor', [
            'accounts' => AuthorizeAccount::query()->orderByDesc('is_active')->orderBy('id')->get(['id', 'name', 'environment', 'is_active']),
            'events' => $this->query()->latest('created_at')->paginate(25),
            'eventStats' => (clone $this->query())->getQuery()->select('event_type')->selectRaw('COUNT(*) as total')
                ->groupBy('event_type')->orderByDesc('total')->limit(8)->get(),
            'summary' => [
                'total_events' => (clone $this->query())->count(),
                'with_payload_id' => (clone $this->query())->whereNotNull('payload_id')->count(),
                'error_count' => count($logEntries['errors']),
                'retry_count' => count($logEntries['retries']),
            ],
            'errorLogs' => $logEntries['errors'],
            'retryLogs' => $logEntries['retries'],
        ])->layout('layouts.app', ['title' => 'Gateway Monitor']);
    }
}
