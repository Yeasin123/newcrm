<?php

namespace App\Livewire\Salesmen;

use App\Models\User;
use App\Services\Commission\PayPeriodService;
use App\Services\Commission\SalesmanCommissionService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Salesman commission tracker — three views:
 *   today      : sales + commission closed today
 *   pay-period : current period totals, admin insights, the next payroll run
 *                (with a Mark Paid action) and payroll history
 *   all-sales  : lifetime KPIs, monthly volume + plan-mix charts, full history
 */
class Show extends Component
{
    public User $salesman;

    #[Url]
    public string $view = 'pay-period'; // today | pay-period | all-sales

    #[Url]
    public string $historyRange = 'all';

    public function mount(User $salesman): void
    {
        abort_unless((int) $salesman->type === 1 || $salesman->role_id !== null, 404);
        $this->salesman = $salesman;
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['today', 'pay-period', 'all-sales'], true) ? $view : 'pay-period';
    }

    public function markPaid(SalesmanCommissionService $svc, PayPeriodService $periods): void
    {
        $p = $periods->current();

        $payroll = $svc->markPaid($this->salesman, $p['start'], $p['end'], $p['payday'], auth()->user());

        Flux::toast(
            variant: 'success',
            text: __('Payroll recorded — :n sales, $:net net.', [
                'n' => $payroll->sales_count,
                'net' => number_format((float) $payroll->net, 2),
            ]),
        );
    }

    public function render(SalesmanCommissionService $svc, PayPeriodService $periods): View
    {
        $data = [
            'rate' => $svc->rate(),
            'periodLabel' => config('commission.pay_period.label', 'Bi-weekly'),
            'nextPayday' => $periods->current()['payday'],
        ];

        if ($this->view === 'today') {
            $data['today'] = $svc->today($this->salesman);
        } elseif ($this->view === 'all-sales') {
            $data['all'] = $svc->allSales($this->salesman);
        } else {
            $data['period'] = $svc->payPeriod($this->salesman);
            $data['insights'] = $svc->adminInsights($this->salesman);
            $data['history'] = $svc->payrollHistory($this->salesman, $this->historyRange);
        }

        return view('livewire.salesmen.show', $data + ['salesman' => $this->salesman])
            ->layout('layouts.app', ['title' => $this->salesman->name]);
    }
}
