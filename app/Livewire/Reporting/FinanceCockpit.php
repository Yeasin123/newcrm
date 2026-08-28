<?php

namespace App\Livewire\Reporting;

use App\Services\Reporting\FinanceCockpitService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Finance Cockpit. Ported from the legacy FinanceCockpitController — one
 * screen with tabs (overview / monthly / salesmen / clients / ledger) over a
 * selectable month range.
 */
class FinanceCockpit extends Component
{
    #[Url]
    public string $tab = 'overview';

    #[Url]
    public int $months = 1;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('Settings'), 403);
    }

    public function render(FinanceCockpitService $svc): View
    {
        $range = $svc->resolveRange($this->months);
        $summary = $svc->summary($range['from'], $range['to']);

        return view('livewire.reporting.finance-cockpit', [
            'range' => $range,
            'summary' => $summary,
            'ledgerRows' => $svc->ledgerRows($summary),
            'monthlySeries' => $svc->monthlySeries($this->tab === 'monthly' ? max(3, $this->months) : 12),
            'salesmen' => $svc->topSalesmen($range['from'], $range['to'], $this->tab === 'salesmen' ? 25 : 10),
            'clients' => $svc->topClients($range['from'], $range['to'], $this->tab === 'clients' ? 25 : 10),
        ])->layout('layouts.app', ['title' => 'Finance Cockpit']);
    }
}
