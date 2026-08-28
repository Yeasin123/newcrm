<?php

namespace App\Livewire\Commissions;

use App\Models\SaleCommission;
use App\Services\Commission\CommissionSettingsService;
use App\Services\Commission\CompanyCommissionService;
use App\Services\Commission\PayPeriodService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Company-wide commission dashboard: pending-payout / paid / clawback KPIs,
 * the per-salesman breakdown, the weekly Billing Spliffy leaderboard, the
 * clawback detail, payroll history, a one-off 2× commission scheduler and
 * "Run Payroll" (mark the whole current period paid).
 */
class Index extends Component
{
    public bool $showSchedule = false;

    public string $sxDate = '';

    public string $sxStart = '18:00';

    public string $sxEnd = '19:00';

    public function mount(CommissionSettingsService $settings): void
    {
        $event = $settings->get('two_x_event');
        $this->sxDate = $event['date'] ?? now()->toDateString();
        $this->sxStart = $event['start'] ?? '18:00';
        $this->sxEnd = $event['end'] ?? '19:00';
        $this->showSchedule = $event !== null;
    }

    public function runPayroll(CompanyCommissionService $company): void
    {
        $runs = $company->runPayroll(auth()->user());

        Flux::toast(
            variant: $runs->isEmpty() ? 'warning' : 'success',
            text: $runs->isEmpty()
                ? __('No unpaid commissions in the current pay period.')
                : __('Payroll run — :n salesmen, $:net net.', [
                    'n' => $runs->count(),
                    'net' => number_format((float) $runs->sum('net'), 2),
                ]),
        );
    }

    public function awardBillingSpliffy(CompanyCommissionService $company): void
    {
        $spliffy = $company->weeklyBillingSpliffy();
        $leader = $spliffy['rows']->first();

        if (! $leader || $leader->charged <= 0) {
            Flux::toast(variant: 'warning', text: __('No billing activity this week yet.'));

            return;
        }

        SaleCommission::create([
            'user_id' => $leader->user->id,
            'type' => 'bonus',
            'policy_number' => 'BILLING-SPLIFFY '.$spliffy['week_start']->format('Y-m-d'),
            'price' => 0,
            'commision_price' => round($spliffy['amount'], 2),
            'store_date' => now(),
            'is_paid' => 0,
            'is_cancel' => 0,
        ]);

        Flux::toast(variant: 'success', text: __('$:n Billing Spliffy awarded to :name — lands in the next payout.', [
            'n' => number_format($spliffy['amount'], 0),
            'name' => $leader->user->name,
        ]));
    }

    public function saveSchedule(CompanyCommissionService $company): void
    {
        $this->validate([
            'sxDate' => 'required|date',
            'sxStart' => 'required',
            'sxEnd' => 'required',
        ]);

        $company->saveTwoXEvent($this->sxDate, $this->sxStart, $this->sxEnd);
        Flux::toast(variant: 'success', text: __('One-time 2× window saved.'));
    }

    public function clearSchedule(CompanyCommissionService $company): void
    {
        $company->saveTwoXEvent(null, $this->sxStart, $this->sxEnd);
        $this->showSchedule = false;
        Flux::toast(variant: 'success', text: __('2× window cleared.'));
    }

    public function render(CompanyCommissionService $company, PayPeriodService $periods): View
    {
        $breakdown = $company->salesmanBreakdown();

        return view('livewire.commissions.index', [
            'overview' => $company->overview(),
            'breakdown' => $breakdown,
            'companyTotals' => [
                'sales' => $breakdown->sum('sales'),
                'payout' => round($breakdown->sum('est_payout'), 2),
            ],
            'spliffy' => $company->weeklyBillingSpliffy(),
            'clawbacks' => $company->clawbackDetail(),
            'payroll' => $company->payrollHistory(),
            'period' => $periods->current(),
        ])->layout('layouts.app', ['title' => 'Commissions']);
    }
}
