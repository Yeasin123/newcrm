<?php

namespace App\Services\Commission;

use App\Models\Client;
use App\Models\SaleCommission;
use App\Models\SalesmanPayroll;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the Salesmen module's commission tracker needs, computed live
 * from `sale_commissions` (+ a `clients` join for customer status). Pay
 * periods come from PayPeriodService; "Mark Paid" flips is_paid and records
 * a `salesman_payrolls` row.
 */
class SalesmanCommissionService
{
    public function __construct(
        private readonly PayPeriodService $periods,
        private readonly CommissionSettingsService $settings,
    ) {}

    /**
     * Stacked Spliffy bonus for a given number of full-sale "cards" in a pay
     * period: every configured tier whose threshold is met pays out, and the
     * amounts add up (3 cards -> $65; 6 cards -> $65 + $130 = $195).
     */
    public function spiffFor(int $cards): float
    {
        if (! $this->settings->get('spiff.enabled', true)) {
            return 0.0;
        }

        return round(collect($this->settings->get('spiff.tiers', []))
            ->filter(fn ($t) => $cards >= (int) ($t['cards'] ?? PHP_INT_MAX))
            ->sum(fn ($t) => (float) ($t['amount'] ?? 0)), 2);
    }

    public function rate(): float
    {
        return (float) config('commission.rate', 0.08);
    }

    /* ---------------------------------------------------------------- today */

    /** @return array{count:int, commission:float} */
    public function today(User $user): array
    {
        $row = SaleCommission::query()
            ->where('user_id', $user->id)
            ->where('is_cancel', 0)
            ->whereDate('store_date', today())
            ->selectRaw('count(*) as c, coalesce(sum(commision_price),0) as total')
            ->first();

        return ['count' => (int) ($row->c ?? 0), 'commission' => (float) ($row->total ?? 0)];
    }

    /* ----------------------------------------------------------- pay period */

    /**
     * @return array{start:Carbon,end:Carbon,payday:Carbon,deals:int,gross:float,spiff:float,est_net:float,rows:Collection}
     */
    public function payPeriod(User $user, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $p = $start && $end
            ? ['start' => $start, 'end' => $end, 'payday' => $this->periods->forDate($start)['payday']]
            : $this->periods->current();

        $rows = SaleCommission::query()
            ->where('user_id', $user->id)
            ->where('is_cancel', 0)
            ->where('is_paid', 0)
            ->whereBetween('store_date', [$p['start']->copy()->startOfDay(), $p['end']->copy()->endOfDay()])
            ->with('client:id,first_name,last_name,email,status,login_off')
            ->orderByDesc('store_date')
            ->get();

        $gross = round((float) $rows->sum('commision_price'), 2);
        // "cards" = full sales in the period (1 sale = 1 card, any # of properties).
        $spiff = $this->spiffFor($rows->count());

        return [
            'start' => $p['start'], 'end' => $p['end'], 'payday' => $p['payday'],
            'deals' => $rows->count(),
            'gross' => $gross,
            'spiff' => $spiff,
            'est_net' => round($gross + $spiff, 2),
            'rows' => $rows,
        ];
    }

    /* -------------------------------------------------------- admin insights */

    public function adminInsights(User $user): array
    {
        $rows = SaleCommission::query()
            ->where('user_id', $user->id)
            ->get(['client_id', 'payment_structure', 'total_year', 'installment', 'is_paid', 'is_cancel']);

        $plans = ['Monthly' => 0, '1-Year' => 0, '2-Year' => 0, 'VIP 3-Yr' => 0, 'VIP 6-Yr' => 0, 'Installment' => 0];
        foreach ($rows as $r) {
            $plans[$this->planLabel($r)]++;
        }

        $clientIds = $rows->pluck('client_id')->filter()->unique();
        $status = $this->customerStatus($clientIds);

        $payrolls = SalesmanPayroll::where('user_id', $user->id);

        return [
            'customers' => $clientIds->count(),
            'plans' => $plans,
            'status' => $status,
            'cancel_rate' => $status['total'] > 0 ? round($status['cancelled'] / $status['total'] * 100, 1) : 0.0,
            'paid_in_full' => $rows->where('installment', '<', 2)->count(),
            'on_installments' => $rows->where('installment', '>=', 2)->count(),
            'payrolls_run' => (clone $payrolls)->count(),
            'all_time_gross' => round((float) (clone $payrolls)->sum('gross'), 2),
            'all_time_net' => round((float) (clone $payrolls)->sum('net'), 2),
        ];
    }

    /* ------------------------------------------------------------ all sales */

    public function allSales(User $user): array
    {
        $rows = SaleCommission::query()
            ->where('user_id', $user->id)
            ->with('client:id,first_name,last_name,email,status,login_off')
            ->orderByDesc('store_date')
            ->limit(300)
            ->get();

        $clientIds = SaleCommission::where('user_id', $user->id)->distinct()->pluck('client_id')->filter();
        $status = $this->customerStatus($clientIds);

        // Monthly sales volume, last 12 calendar months (driver-portable).
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', store_date)"
            : "date_format(store_date, '%Y-%m')";
        $byMonth = SaleCommission::query()
            ->where('user_id', $user->id)
            ->where('store_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$monthExpr} as ym, count(*) as c")
            ->groupBy('ym')->pluck('c', 'ym');

        $labels = [];
        $data = [];
        foreach (range(11, 0) as $back) {
            $m = now()->subMonths($back);
            $labels[] = $m->format('M y');
            $data[] = (int) ($byMonth[$m->format('Y-m')] ?? 0);
        }

        // Plan mix (all-time).
        $mix = [];
        foreach (SaleCommission::where('user_id', $user->id)->get(['payment_structure', 'total_year', 'installment']) as $r) {
            $label = $this->planLabel($r);
            $mix[$label] = ($mix[$label] ?? 0) + 1;
        }
        arsort($mix);

        $payrolls = SalesmanPayroll::where('user_id', $user->id);
        $yearlyValues = $rows->filter(fn ($r) => (string) $r->payment_structure !== '1' && $r->price > 0)->pluck('price');

        return [
            'total_customers' => $status['total'],
            'active' => $status['active'],
            'cancelled' => $status['cancelled'],
            'all_time_gross' => round((float) (clone $payrolls)->sum('gross'), 2),
            'all_time_net' => round((float) (clone $payrolls)->sum('net'), 2),
            'payrolls_run' => (clone $payrolls)->count(),
            'avg_plan_value' => $yearlyValues->count() ? round($yearlyValues->avg(), 2) : 0.0,
            'chart_labels' => $labels,
            'chart_data' => $data,
            'plan_mix' => $mix,
            'rows' => $rows->take(100),
        ];
    }

    /* -------------------------------------------------------- payroll history */

    public function payrollHistory(User $user, string $range = 'all'): Collection
    {
        $q = SalesmanPayroll::where('user_id', $user->id)->latest('pay_date');

        [$from, $to] = match ($range) {
            'this_period' => [$this->periods->current()['start'], $this->periods->current()['end']],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'last_3_months' => [now()->subMonths(3)->startOfDay(), now()],
            default => [null, null],
        };

        if ($from && $to) {
            $q->whereBetween('pay_date', [$from, $to]);
        }

        return $q->with('runBy:id,first_name,last_name,email')->limit(60)->get();
    }

    /* ------------------------------------------------------------- mark paid */

    public function markPaid(User $user, Carbon $start, Carbon $end, Carbon $payday, ?User $by = null): SalesmanPayroll
    {
        return DB::transaction(function () use ($user, $start, $end, $payday, $by) {
            $scope = SaleCommission::query()
                ->where('user_id', $user->id)
                ->where('is_cancel', 0)
                ->where('is_paid', 0)
                ->whereBetween('store_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

            $agg = (clone $scope)->selectRaw('count(*) as c, coalesce(sum(commision_price),0) as gross')->first();
            $count = (int) ($agg->c ?? 0);
            $gross = round((float) ($agg->gross ?? 0), 2);
            $spiff = $this->spiffFor($count);

            (clone $scope)->update(['is_paid' => 1]);

            return SalesmanPayroll::create([
                'user_id' => $user->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'pay_date' => $payday->toDateString(),
                'sales_count' => $count,
                'gross' => $gross,
                'spiff' => $spiff,
                'net' => round($gross + $spiff, 2),
                'run_by_user_id' => $by?->id,
                'run_by_email' => $by?->email,
            ]);
        });
    }

    /* --------------------------------------------------------------- helpers */

    public function planLabel(object $row): string
    {
        if ((int) ($row->installment ?? 0) >= 2) {
            return 'Installment';
        }

        if ((string) ($row->payment_structure ?? '') === '1') {
            return 'Monthly';
        }

        return match ((int) ($row->total_year ?? 1)) {
            2 => '2-Year',
            3, 4 => 'VIP 3-Yr',
            5, 6 => 'VIP 6-Yr',
            default => '1-Year',
        };
    }

    /**
     * @param  Collection<int, int>  $clientIds
     * @return array{total:int, active:int, cancelled:int, failed:int, renewal:int, bypass:int}
     */
    private function customerStatus(Collection $clientIds): array
    {
        if ($clientIds->isEmpty()) {
            return ['total' => 0, 'active' => 0, 'cancelled' => 0, 'failed' => 0, 'renewal' => 0, 'bypass' => 0];
        }

        $c = Client::query()->whereIn('id', $clientIds)
            ->selectRaw('
                count(*) as total,
                sum(case when status = 1 and (login_off = 0 or login_off is null) then 1 else 0 end) as active,
                sum(case when login_off = 1 or status = 0 then 1 else 0 end) as cancelled,
                sum(case when status = 5 then 1 else 0 end) as bypass,
                sum(case when is_renew = 1 then 1 else 0 end) as renewal,
                sum(case when disputed is not null and disputed <> \'\' then 1 else 0 end) as failed
            ')
            ->first();

        return [
            'total' => (int) $c->total,
            'active' => (int) $c->active,
            'cancelled' => (int) $c->cancelled,
            'failed' => (int) $c->failed,
            'renewal' => (int) $c->renewal,
            'bypass' => (int) $c->bypass,
        ];
    }
}
