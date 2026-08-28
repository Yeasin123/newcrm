<?php

namespace App\Services\Commission;

use App\Models\SaleCommission;
use App\Models\SalesmanPayroll;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Company-wide commission roll-ups for the Commissions dashboard, plus
 * "Run Payroll" (mark every salesman's current period paid) and the weekly
 * Billing Spliffy leaderboard + clawback detail.
 */
class CompanyCommissionService
{
    public function __construct(
        private readonly PayPeriodService $periods,
        private readonly SalesmanCommissionService $salesman,
        private readonly CommissionSettingsService $settings,
    ) {}

    /**
     * @return array{pending:float, paid_all_time:float, payroll_runs:int, active_salesmen:int, total_salesmen:int, clawbacks_pending:float, next_payday:Carbon}
     */
    public function overview(): array
    {
        $p = $this->periods->current();

        $pending = (float) SaleCommission::query()
            ->whereNotNull('user_id')->where('is_cancel', 0)->where('is_paid', 0)
            ->whereBetween('store_date', [$p['start']->copy()->startOfDay(), $p['end']->copy()->endOfDay()])
            ->sum('commision_price');

        $clawbacks = (float) SaleCommission::query()
            ->where('is_cancel', 1)->where('is_cancel_deducted', 0)
            ->sum('commision_price');

        return [
            'pending' => round($pending, 2),
            'paid_all_time' => round((float) SalesmanPayroll::sum('net'), 2),
            'payroll_runs' => SalesmanPayroll::count(),
            'active_salesmen' => User::where('type', 1)->where('status', 1)->count(),
            'total_salesmen' => User::where('type', 1)->count(),
            'clawbacks_pending' => round($clawbacks, 2),
            'next_payday' => $p['payday'],
        ];
    }

    /**
     * Per-salesman unpaid-period rollup, richest first.
     *
     * @return Collection<int, object{user:User, rate:float, sales:int, est_payout:float}>
     */
    public function salesmanBreakdown(): Collection
    {
        $p = $this->periods->current();

        $agg = SaleCommission::query()
            ->whereNotNull('user_id')->where('is_cancel', 0)->where('is_paid', 0)
            ->whereBetween('store_date', [$p['start']->copy()->startOfDay(), $p['end']->copy()->endOfDay()])
            ->selectRaw('user_id, count(*) as sales, coalesce(sum(commision_price),0) as gross')
            ->groupBy('user_id')->get()->keyBy('user_id');

        return User::query()->where('type', 1)->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'status'])
            ->map(function (User $u) use ($agg) {
                $row = $agg->get($u->id);

                return (object) [
                    'user' => $u,
                    'rate' => $this->settings->rateFor($u->id),
                    'sales' => (int) ($row->sales ?? 0),
                    'est_payout' => round((float) ($row->gross ?? 0), 2),
                ];
            })
            ->sortByDesc('est_payout')
            ->values();
    }

    /**
     * Weekly Billing Spliffy leaderboard — who charged the most this week
     * (bypass sales excluded). Ranked; the leader is eligible for the bonus.
     *
     * @return array{week_start:Carbon, week_end:Carbon, amount:float, rows:Collection}
     */
    public function weeklyBillingSpliffy(): array
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $agg = SaleCommission::query()
            ->whereNotNull('user_id')->where('is_cancel', 0)
            ->whereBetween('store_date', [$start, $end])
            ->selectRaw('user_id, coalesce(sum(price),0) as charged')
            ->groupBy('user_id')->pluck('charged', 'user_id');

        $rows = User::query()->where('type', 1)->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u) => (object) ['user' => $u, 'charged' => round((float) ($agg[$u->id] ?? 0), 2)])
            ->sortByDesc('charged')
            ->values();

        return [
            'week_start' => $start,
            'week_end' => $end,
            'amount' => (float) $this->settings->get('billing_spliffy.amount', 200),
            'enabled' => (bool) $this->settings->get('billing_spliffy.enabled', true),
            'rows' => $rows,
        ];
    }

    /**
     * Cancelled / disputed sales whose commission is pending clawback
     * (is_cancel = 1, not yet deducted), newest first.
     *
     * @return array{rows:Collection, total:float, window_days:int}
     */
    public function clawbackDetail(int $windowDays = 60): array
    {
        $rows = SaleCommission::query()
            ->where('is_cancel', 1)->where('is_cancel_deducted', 0)
            ->where('updated_at', '>=', now()->subDays($windowDays))
            ->with(['salesman:id,first_name,last_name', 'client:id,first_name,last_name,status,login_off,disputed,created_at'])
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return [
            'rows' => $rows,
            'total' => round((float) $rows->sum('commision_price'), 2),
            'window_days' => $windowDays,
        ];
    }

    /**
     * Run payroll for the current pay period across every salesman that has
     * unpaid sales in it. Returns the payroll rows created.
     *
     * @return Collection<int, SalesmanPayroll>
     */
    public function runPayroll(?User $by = null): Collection
    {
        $p = $this->periods->current();

        $userIds = SaleCommission::query()
            ->whereNotNull('user_id')->where('is_cancel', 0)->where('is_paid', 0)
            ->whereBetween('store_date', [$p['start']->copy()->startOfDay(), $p['end']->copy()->endOfDay()])
            ->distinct()->pluck('user_id');

        return $userIds->map(function ($uid) use ($p, $by) {
            $user = User::find($uid);

            return $user ? $this->salesman->markPaid($user, $p['start'], $p['end'], $p['payday'], $by) : null;
        })->filter()->values();
    }

    /**
     * Persist the one-off 2× commission window from the dashboard banner.
     */
    public function saveTwoXEvent(?string $date, string $start, string $end): void
    {
        $cfg = $this->settings->all();
        $cfg['two_x_event'] = $date ? ['date' => $date, 'start' => $start, 'end' => $end] : null;
        $this->settings->save($cfg);
    }

    /** @return array{list:Collection} recent payroll runs, newest first */
    public function payrollHistory(int $limit = 40): array
    {
        return [
            'list' => SalesmanPayroll::query()
                ->with(['salesman:id,first_name,last_name', 'runBy:id,first_name,last_name,email'])
                ->latest('pay_date')->latest('id')
                ->limit($limit)->get(),
        ];
    }

    /** All-time plan-mix + status donut data per salesman for the stats grid. */
    public function salesmanStats(): Collection
    {
        return DB::table('sale_commissions')
            ->whereNotNull('user_id')
            ->selectRaw('user_id,
                count(*) as sales,
                count(distinct client_id) as customers,
                coalesce(sum(commision_price),0) as gross')
            ->groupBy('user_id')->get()->keyBy('user_id');
    }
}
