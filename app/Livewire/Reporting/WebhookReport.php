<?php

namespace App\Livewire\Reporting;

use App\Services\Reporting\EntranceReportService;
use App\Services\Reporting\WebhookReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Webhook Report. Ported from WebhookReportController — a per-day matrix of
 * inbound webhook flags across every source column, with a click-through to
 * the rows behind one day + source. (The heavy entrance sale-attribution
 * report is deferred — see the phase plan.)
 */
class WebhookReport extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'daily'; // daily | entrance

    public ?string $detailDate = null;

    public ?string $detailType = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('Settings'), 403);
    }

    public function updatedView(): void
    {
        $this->resetPage();
        $this->closeDetail();
    }

    public function showDetail(string $date, string $type): void
    {
        $this->detailDate = $date;
        $this->detailType = $type;
    }

    public function closeDetail(): void
    {
        $this->detailDate = null;
        $this->detailType = null;
    }

    public function render(WebhookReportService $svc, EntranceReportService $entrance): View
    {
        return view('livewire.reporting.webhook-report', [
            'sources' => WebhookReportService::SOURCES,
            'rows' => $this->view === 'daily' ? $svc->dailyCounts() : collect(),
            'entranceLabels' => array_values(EntranceReportService::ENTRANCE_KEYS),
            'entranceRows' => $this->view === 'entrance' ? $entrance->dailyRows(30) : null,
            'detail' => $this->detailDate && $this->detailType
                ? $svc->detail($this->detailDate, $this->detailType)
                : null,
        ])->layout('layouts.app', ['title' => 'Webhook Report']);
    }
}
