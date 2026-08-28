<?php

namespace App\Services\Earnings;

use App\Models\SaleCommission;
use App\Models\SalesmanPayroll;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Revenue / commission / net-earnings analytics for the Earnings module.
 * One cached pass over `clients` (JSON price decode per row) produces the
 * MRR / plan-type / package / status roll-ups; commission figures come from
 * `salesman_payrolls` (paid) + unpaid `sale_commissions` (pending).
 *
 * MRR = monthly-equivalent revenue per active customer:
 *   - monthly plan            -> the monthly charge
 *   - installment plan (2..5) -> the recurring installment amount
 *   - yearly full             -> plan total ÷ term months
 */
class EarningsAnalyticsService
{
    private const KEY = 'earnings:analytics:v1';

    private const TTL = 900;

    private const PLAN_LABELS = [
        'monthly' => 'Monthly',
        '1year' => '1-Year',
        '2year' => '2-Year',
        'vip3' => 'VIP 3-Year',
        'vip6' => 'VIP 6-Year',
        'installment' => 'Yearly (Install.)',
    ];

    /* ============================================================ per-client */

    /** @param object $c row with installment, discount, payment_structure, start_date, end_date, price_cal_response */
    public function mrrFor(object $c): float
    {
        $p = json_decode((string) ($c->price_cal_response ?? ''), true) ?: [];
        $num = fn ($v) => (float) str_replace(',', '', (string) ($v ?? 0));

        $planTotal = $num($p['discount_price'] ?? 0) ?: $num($p['price'] ?? 0);

        if ((int) ($c->installment ?? 0) >= 2) {
            $inst = $num($p['installment_price'] ?? 0);

            return $inst ?: ($planTotal ? round($planTotal / max(1, (int) $c->installment), 2) : 0.0);
        }

        if ((string) $c->payment_structure === '1') {
            return $planTotal;
        }

        // Yearly full — monthly-equivalent over the term.
        $months = $this->termMonths($c);

        return $months > 0 ? round($planTotal / $months, 2) : $planTotal;
    }

    public function planKey(object $c): string
    {
        if ((int) ($c->installment ?? 0) >= 2) {
            return 'installment';
        }

        if ((string) $c->payment_structure === '1') {
            return 'monthly';
        }

        return match ($this->termYears($c)) {
            2 => '2year',
            3, 4 => 'vip3',
            5, 6 => 'vip6',
            default => '1year',
        };
    }

    private function termMonths(object $c): int
    {
        if ($c->start_date && $c->end_date) {
            try {
                return max(1, (int) round(Carbon::parse($c->start_date)->diffInMonths(Carbon::parse($c->end_date))));
            } catch (\Throwable) {
            }
        }

        return 12;
    }

    private function termYears(object $c): int
    {
        return max(1, (int) round($this->termMonths($c) / 12));
    }

    private function statusKey(object $c): string
    {
        return match (true) {
            (int) $c->status === 5 => 'bypass',
            $c->login_off && filled($c->cancle_date) => 'cancelled',
            (bool) $c->is_renew && (int) $c->status === 1 => 'renewal',
            filled($c->disputed) => 'failed',
            (int) $c->status === 0 && $c->login_off => 'inactive',
            (int) $c->status === 0 => 'suspended',
            default => 'active',
        };
    }

    /* ========================================================== cached pass */

    /**
     * One roll-up of every client: per plan type, per package, per status.
     */
    public function portfolio(): array
    {
        return Cache::remember(self::KEY.':portfolio', self::TTL, function () {
            $plan = [];
            $pkg = [];
            $status = [];
            $activeMrr = 0.0;
            $activeCount = 0;
            $total = 0;
            $monthlyMrr = 0.0;
            $monthlyCount = 0;
            $instMrr = 0.0;
            $instCount = 0;

            $packages = DB::table('packages')->pluck('title', 'id');

            DB::table('clients')->whereNull('deleted_at')
                ->select('id', 'status', 'login_off', 'disputed', 'is_renew', 'cancle_date',
                    'payment_structure', 'installment', 'discount', 'start_date', 'end_date',
                    'price_cal_response', 'package')
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$plan, &$pkg, &$status, &$activeMrr, &$activeCount, &$total, &$monthlyMrr, &$monthlyCount, &$instMrr, &$instCount, $packages) {
                    foreach ($rows as $c) {
                        $total++;
                        $sk = $this->statusKey($c);
                        $status[$sk] ??= ['count' => 0, 'mrr' => 0.0];
                        $status[$sk]['count']++;

                        $isActive = $sk === 'active';
                        if (! $isActive) {
                            continue;
                        }

                        $mrr = $this->mrrFor($c);
                        $pk = $this->planKey($c);

                        $status[$sk]['mrr'] += $mrr;
                        $activeMrr += $mrr;
                        $activeCount++;

                        $plan[$pk] ??= ['count' => 0, 'mrr' => 0.0];
                        $plan[$pk]['count']++;
                        $plan[$pk]['mrr'] += $mrr;

                        $name = $packages[$c->package] ?? 'undefined';
                        $pkg[$name] ??= ['count' => 0, 'mrr' => 0.0];
                        $pkg[$name]['count']++;
                        $pkg[$name]['mrr'] += $mrr;

                        if ($pk === 'monthly') {
                            $monthlyMrr += $mrr;
                            $monthlyCount++;
                        } elseif ($pk === 'installment') {
                            $instMrr += $mrr;
                            $instCount++;
                        }
                    }
                });

            return [
                'plan' => $plan,
                'package' => $pkg,
                'status' => $status,
                'active_mrr' => round($activeMrr, 2),
                'active_count' => $activeCount,
                'total_policies' => $total,
                'monthly_mrr' => round($monthlyMrr, 2),
                'monthly_count' => $monthlyCount,
                'inst_mrr' => round($instMrr, 2),
                'inst_count' => $instCount,
            ];
        });
    }

    /* ============================================================ commissions */

    /** @return array{paid:float, pending:float, clawbacks:float, payroll_runs:int} */
    public function commissionTotals(): array
    {
        return [
            'paid' => round((float) SalesmanPayroll::sum('net'), 2),
            'pending' => round((float) SaleCommission::where('is_cancel', 0)->where('is_paid', 0)->sum('commision_price'), 2),
            'clawbacks' => round((float) SaleCommission::where('is_cancel', 1)->where('is_cancel_deducted', 0)->sum('commision_price'), 2),
            'payroll_runs' => SalesmanPayroll::count(),
        ];
    }

    /* ============================================================ overview tab */

    public function overview(): array
    {
        $p = $this->portfolio();
        $c = $this->commissionTotals();
        $grossArr = round($p['active_mrr'] * 12, 2);
        $expense = round($c['paid'] + $c['pending'], 2);

        return [
            'gross_mrr' => $p['active_mrr'],
            'gross_arr' => $grossArr,
            'active_customers' => $p['active_count'],
            'total_policies' => $p['total_policies'],
            'avg_per_customer' => $p['active_count'] ? round($p['active_mrr'] / $p['active_count'], 2) : 0.0,
            'monthly_mrr' => $p['monthly_mrr'],
            'monthly_count' => $p['monthly_count'],
            'inst_mrr' => $p['inst_mrr'],
            'inst_count' => $p['inst_count'],
            'recurring_mrr' => round($p['monthly_mrr'] + $p['inst_mrr'], 2),
            'commissions_paid' => $c['paid'],
            'commissions_pending' => $c['pending'],
            'payroll_runs' => $c['payroll_runs'],
            'commission_rate' => $grossArr > 0 ? round($expense / $grossArr * 100, 1) : 0.0,
            'net_arr' => round($grossArr - $expense, 2),
            'net_margin' => $grossArr > 0 ? round(($grossArr - $expense) / $grossArr * 100, 1) : 0.0,
        ];
    }

    /** New-customer cash vs. payroll paid in a date range. */
    public function snapshot(Carbon $from, Carbon $to, string $mode = 'cash'): array
    {
        $newClients = DB::table('clients')->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, $to])
            ->select('id', 'payment_structure', 'installment', 'discount', 'start_date', 'end_date', 'price_cal_response')
            ->get();

        $revenue = 0.0;
        foreach ($newClients as $c) {
            $p = json_decode((string) ($c->price_cal_response ?? ''), true) ?: [];
            $num = fn ($v) => (float) str_replace(',', '', (string) ($v ?? 0));
            $full = $num($p['discount_price'] ?? 0) ?: $num($p['price'] ?? 0);

            if ($mode === 'full') {
                $revenue += $full;
            } elseif ((int) ($c->installment ?? 0) >= 2) {
                $revenue += $num($p['installment_price'] ?? 0) ?: ($full ? $full / max(1, (int) $c->installment) : 0);
            } elseif ((string) $c->payment_structure === '1') {
                $revenue += $full;
            } else {
                $revenue += $full;
            }
        }

        $payrolls = SalesmanPayroll::whereBetween('pay_date', [$from->toDateString(), $to->toDateString()]);
        $commPaid = round((float) (clone $payrolls)->sum('net'), 2);

        return [
            'revenue' => round($revenue, 2),
            'new_customers' => $newClients->count(),
            'commissions' => $commPaid,
            'payroll_runs' => (clone $payrolls)->count(),
            'net' => round($revenue - $commPaid, 2),
            'net_margin' => $revenue > 0 ? round(($revenue - $commPaid) / $revenue * 100, 1) : 0.0,
        ];
    }

    /** salesman_payrolls net grouped by month, last 12. */
    public function payoutsByMonth(): array
    {
        $expr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', pay_date)"
            : "date_format(pay_date, '%Y-%m')";

        $byMonth = SalesmanPayroll::query()
            ->where('pay_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$expr} as ym, coalesce(sum(net),0) as n")
            ->groupBy('ym')->pluck('n', 'ym');

        $labels = [];
        $data = [];
        foreach (range(11, 0) as $back) {
            $m = now()->subMonths($back);
            $labels[] = $m->format('M y');
            $data[] = round((float) ($byMonth[$m->format('Y-m')] ?? 0), 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /* ============================================================ per salesman */

    /**
     * Per-salesman MRR (from their active customers), ARR, commission paid /
     * pending, net ARR + margin. Company total appended by the caller.
     *
     * @return Collection<int, object>
     */
    public function bySalesman(): Collection
    {
        // Not cached: the payload is Eloquent models, and one chunked pass
        // over the active-client rows is cheap enough per render.
        return (function () {
            $users = User::where('type', 1)->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'status'])->keyBy('id');

            $nameToId = $users->mapWithKeys(fn ($u) => [strtolower(trim($u->name)) => $u->id]);

            $mrrByUser = [];
            $custByUser = [];

            DB::table('clients')->whereNull('deleted_at')->where('status', 1)
                ->whereRaw('(login_off = 0 or login_off is null)')
                ->select('id', 'salesman_name', 'payment_structure', 'installment', 'discount', 'start_date', 'end_date', 'price_cal_response')
                ->orderBy('id')
                ->chunk(1000, function ($rows) use (&$mrrByUser, &$custByUser, $nameToId) {
                    foreach ($rows as $c) {
                        $uid = $nameToId[strtolower(trim((string) $c->salesman_name))] ?? null;
                        if (! $uid) {
                            continue;
                        }
                        $mrrByUser[$uid] = ($mrrByUser[$uid] ?? 0) + $this->mrrFor($c);
                        $custByUser[$uid] = ($custByUser[$uid] ?? 0) + 1;
                    }
                });

            $paid = SalesmanPayroll::selectRaw('user_id, coalesce(sum(net),0) as n')->groupBy('user_id')->pluck('n', 'user_id');
            $pending = SaleCommission::where('is_cancel', 0)->where('is_paid', 0)
                ->selectRaw('user_id, coalesce(sum(commision_price),0) as n')->groupBy('user_id')->pluck('n', 'user_id');
            $totalCust = SaleCommission::whereNotNull('user_id')
                ->selectRaw('user_id, count(distinct client_id) as n')->groupBy('user_id')->pluck('n', 'user_id');

            return $users->map(function (User $u) use ($mrrByUser, $custByUser, $paid, $pending, $totalCust) {
                $mrr = round((float) ($mrrByUser[$u->id] ?? 0), 2);
                $arr = round($mrr * 12, 2);
                $commPaid = round((float) ($paid[$u->id] ?? 0), 2);
                $commPend = round((float) ($pending[$u->id] ?? 0), 2);
                $netArr = round($arr - $commPaid - $commPend, 2);

                return (object) [
                    'user' => $u,
                    'customers_active' => (int) ($custByUser[$u->id] ?? 0),
                    'customers_total' => (int) ($totalCust[$u->id] ?? 0),
                    'mrr' => $mrr,
                    'arr' => $arr,
                    'comm_paid' => $commPaid,
                    'comm_pending' => $commPend,
                    'clawbacks' => 0.0,
                    'net_arr' => $netArr,
                    'net_margin' => $arr > 0 ? round($netArr / $arr * 100, 1) : 0.0,
                ];
            })->sortByDesc('mrr')->values();
        })();
    }

    public function payrollHistory(int $limit = 60): Collection
    {
        return SalesmanPayroll::query()
            ->with(['salesman:id,first_name,last_name', 'runBy:id,first_name,last_name,email'])
            ->latest('pay_date')->latest('id')->limit($limit)->get();
    }

    public function planLabel(string $key): string
    {
        return self::PLAN_LABELS[$key] ?? ucfirst($key);
    }

    public function flush(): void
    {
        Cache::forget(self::KEY.':portfolio');
        Cache::forget(self::KEY.':by-salesman');
    }
}
