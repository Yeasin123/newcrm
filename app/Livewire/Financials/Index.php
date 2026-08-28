<?php

namespace App\Livewire\Financials;

use App\Models\PaymentHistory;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Financials — processor Net P&L: Authorize.net gross minus processor
 * (Zen) fees, reserve holds and chargebacks.
 *
 * Gross revenue, refunds and chargebacks come from payment_histories. Fees
 * and reserve holds need a processor statement feed (Zen) that is not wired
 * yet, so those read $0 and the Statements / Reserves / Payouts /
 * Authorizations tabs show a connect-prompt until that feed exists.
 */
class Index extends Component
{
    #[Url]
    public string $range = 'this_month'; // this_month | last_month | custom

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $tab = 'statements'; // statements | reserves | payouts | authorizations

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['this_month', 'last_month', 'custom'], true) ? $range : 'this_month';
    }

    public function refresh(): void
    {
        Flux::toast(text: __('Refreshed.'));
    }

    public function syncZen(): void
    {
        Flux::toast(
            variant: 'warning',
            text: __('Processor statement sync is not connected — set the Zen credentials to pull statements, reserves and payouts.'),
        );
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function window(): array
    {
        return match ($this->range) {
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'custom' => [
                $this->from !== '' ? Carbon::parse($this->from)->startOfDay() : Carbon::now()->startOfMonth(),
                $this->to !== '' ? Carbon::parse($this->to)->endOfDay() : Carbon::now()->endOfDay(),
            ],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };
    }

    private function metricsFor(Carbon $start, Carbon $end): array
    {
        $gross = (float) PaymentHistory::query()
            ->where('payment_status', 1)->where('claim_payment', 0)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $refunds = (float) PaymentHistory::query()
            ->where('refund_payment', 1)
            ->whereBetween('created_at', [$start, $end])
            ->sum('refund_amount');

        $chargebacks = (float) PaymentHistory::query()
            ->where('disputed', 1)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $fees = 0.0;      // needs the processor statement feed
        $reserves = 0.0;  // needs the processor statement feed
        $net = $gross - $refunds - $fees - $reserves - $chargebacks;

        return compact('gross', 'refunds', 'chargebacks', 'fees', 'reserves', 'net');
    }

    private function monthlyChart(): array
    {
        $labels = [];
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $m = $this->metricsFor($month->copy()->startOfMonth(), $month->copy()->endOfMonth());
            $labels[] = $month->format('M y');
            $data[] = round($m['net'], 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    public function render(): View
    {
        [$start, $end] = $this->window();

        return view('livewire.financials.index', [
            'metrics' => $this->metricsFor($start, $end),
            'chart' => $this->monthlyChart(),
            'periodLabel' => $start->format('M j').' – '.$end->format('M j, Y'),
        ])->layout('layouts.app', ['title' => 'Financials']);
    }
}
