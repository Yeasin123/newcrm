<?php

namespace App\Livewire\Earnings;

use App\Services\Earnings\EarningsAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Earnings — company revenue, commission expense and net earnings across all
 * salesmen. Four tabs: Overview (headline MRR/ARR + net-after-commissions +
 * a cash snapshot), Revenue (plan-type / package / status breakdowns),
 * Commissions (paid / pending / clawback per rep + payroll history) and
 * By Salesman (MRR-vs-commission per rep). All figures come from
 * EarningsAnalyticsService (cached).
 */
class Index extends Component
{
    #[Url]
    public string $tab = 'overview'; // overview | revenue | commissions | salesman

    #[Url]
    public string $snapRange = 'this_month';

    #[Url]
    public string $snapMode = 'cash'; // cash | full

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['overview', 'revenue', 'commissions', 'salesman'], true) ? $tab : 'overview';
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function snapWindow(): array
    {
        return match ($this->snapRange) {
            'last_2_weeks' => [now()->subWeeks(2)->startOfDay(), now()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'last_3_months' => [now()->subMonths(3)->startOfDay(), now()],
            'last_6_months' => [now()->subMonths(6)->startOfDay(), now()],
            'all_time' => [Carbon::parse('2015-01-01'), now()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function render(EarningsAnalyticsService $svc): View
    {
        $data = ['tab' => $this->tab];

        if ($this->tab === 'overview') {
            [$from, $to] = $this->snapWindow();
            $data['overview'] = $svc->overview();
            $data['snapshot'] = $svc->snapshot($from, $to, $this->snapMode === 'full' ? 'full' : 'cash');
            $data['payouts'] = $svc->payoutsByMonth();
        } elseif ($this->tab === 'revenue') {
            $p = $svc->portfolio();
            $data['portfolio'] = $p;
            $data['planLabel'] = fn ($k) => $svc->planLabel($k);
        } elseif ($this->tab === 'commissions') {
            $data['totals'] = $svc->commissionTotals();
            $data['rows'] = $svc->bySalesman();
            $data['history'] = $svc->payrollHistory();
        } else {
            $data['rows'] = $svc->bySalesman();
        }

        return view('livewire.earnings.index', $data)->layout('layouts.app', ['title' => 'Earnings']);
    }
}
