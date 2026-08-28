<?php

namespace App\Services\Reporting;

use App\Models\Claim;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Models\PaymentHistory;
use App\Models\Technician;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The admin dashboard aggregates. Ported from the legacy DashboardController
 * (dashboard / financialSnapshot / financialChartData / operationalChartData).
 * Formulas are kept verbatim; the only change is portability — the month key
 * is driver-aware (MySQL DATE_FORMAT vs sqlite strftime) and the JSON
 * price-calculation sums are done in PHP (as the legacy already did for
 * expected earnings).
 */
class DashboardMetricsService
{
    public function all(): array
    {
        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();
        $prevStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $analytics = Cache::remember('dashboard_metrics_v1_'.$from->format('Y-m'), now()->addMinutes(5), function () use ($from, $to, $prevStart) {
            $current = $this->financialSnapshot($from, $to);
            $previous = $this->financialSnapshot($prevStart, $prevStart->copy()->endOfMonth());

            return [
                'monthly_financials' => array_merge($current, [
                    'period' => $from->format('F Y'),
                    'previous_period' => $prevStart->format('F Y'),
                    'previous' => $previous,
                    'collection_rate' => $current['payment_attempts'] > 0
                        ? round(($current['successful_payments'] / $current['payment_attempts']) * 100, 1) : 0,
                    'refund_rate' => $current['gross_revenue'] > 0
                        ? round(($current['refunds'] / $current['gross_revenue']) * 100, 1) : 0,
                    'dispute_rate' => $current['gross_revenue'] > 0
                        ? round(($current['disputes'] / $current['gross_revenue']) * 100, 1) : 0,
                ]),
                'financial_chart' => $this->financialChart(),
                'operational_chart' => $this->operationalChart(),
            ];
        });

        return array_merge(['scalars' => $this->scalars($from, $to)], $analytics);
    }

    /** The top-line tile numbers. */
    public function scalars(Carbon $from, Carbon $to): array
    {
        $yearFrom = Carbon::now()->startOfYear();
        $yearTo = Carbon::now()->endOfYear();

        $paidPolicy = fn () => PaymentHistory::query()->where('claim_payment', 0)->where('payment_status', 1)->where('refund_payment', 0);

        return [
            'clients' => Client::count(),
            'active_customers' => Client::where('status', 1)->count(),
            'leads' => LeadDetail::count(),
            'claims' => Claim::count(),
            'technicians' => Technician::count(),
            'dispute_clients' => Client::whereNotNull('disputed')->count(),
            'new_clients_today' => Client::whereDate('created_at', Carbon::today())->count(),
            'unsuccessful_payment_clients' => DB::table('payment_histories')
                ->join('clients', 'payment_histories.client_id', '=', 'clients.id')
                ->whereNotIn('clients.authorize_status', ['active', 'canceled', 'expired', 'terminated'])
                ->where('clients.status', '!=', 1)
                ->distinct()->count('clients.id'),
            'total_earnings' => (float) $paidPolicy()->sum('amount'),
            'todays_earnings' => (float) $paidPolicy()->whereDate('created_at', Carbon::today())->sum('amount'),
            'month_earnings' => (float) $paidPolicy()->whereBetween('created_at', [$from, $to])->sum('amount'),
            'year_earnings' => (float) $paidPolicy()->whereBetween('created_at', [$yearFrom, $yearTo])->sum('amount'),
            'total_refund' => (float) PaymentHistory::where('claim_payment', 0)->where('refund_payment', 1)->sum('amount'),
            'refunded_clients' => PaymentHistory::where('claim_payment', 0)->where('refund_payment', 1)->distinct()->count('client_id'),
            'claim_spend' => (float) PaymentHistory::whereNotNull('claim_id')->sum('amount'),
            'claim_not_refunded' => (float) PaymentHistory::where('claim_payment', 1)->where('refund_claim', 0)->sum('amount'),
            'cancelled_policies' => $this->cancelledPolicyCount(),
            'expected_earnings' => $this->expectedEarnings(),
        ];
    }

    public function financialSnapshot(Carbon $from, Carbon $to): array
    {
        $t = PaymentHistory::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 THEN amount ELSE 0 END) as gross_revenue')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND refund_payment = 1 THEN COALESCE(NULLIF(refund_amount, 0), amount) ELSE 0 END) as refunds')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND disputed = 1 THEN amount ELSE 0 END) as disputes')
            ->selectRaw('SUM(CASE WHEN claim_payment = 1 AND payment_status = 1 AND refund_claim = 0 THEN amount ELSE 0 END) as claim_costs')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 THEN 1 ELSE 0 END) as successful_payments')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 0 THEN 1 ELSE 0 END) as failed_payments')
            ->first();

        $gross = (float) ($t->gross_revenue ?? 0);
        $refunds = (float) ($t->refunds ?? 0);
        $disputes = (float) ($t->disputes ?? 0);
        $claimCosts = (float) ($t->claim_costs ?? 0);
        $ok = (int) ($t->successful_payments ?? 0);
        $failed = (int) ($t->failed_payments ?? 0);

        return [
            'gross_revenue' => $gross,
            'refunds' => $refunds,
            'disputes' => $disputes,
            'claim_costs' => $claimCosts,
            'estimated_net' => $gross - $refunds - $disputes - $claimCosts,
            'new_customers' => Client::whereBetween('created_at', [$from, $to])->count(),
            'claims_opened' => Claim::whereBetween('created_at', [$from, $to])->count(),
            'successful_payments' => $ok,
            'failed_payments' => $failed,
            'payment_attempts' => $ok + $failed,
        ];
    }

    public function financialChart(): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $key = $this->monthKey('created_at');

        $rows = PaymentHistory::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("$key as period")
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 THEN amount ELSE 0 END) as gross_revenue')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND refund_payment = 1 THEN COALESCE(NULLIF(refund_amount, 0), amount) ELSE 0 END) as refunds')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND disputed = 1 THEN amount ELSE 0 END) as disputes')
            ->selectRaw('SUM(CASE WHEN claim_payment = 1 AND payment_status = 1 AND refund_claim = 0 THEN amount ELSE 0 END) as claim_costs')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 THEN 1 ELSE 0 END) as successful_payments')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 0 THEN 1 ELSE 0 END) as failed_payments')
            ->groupByRaw($key)
            ->get()
            ->keyBy('period');

        $chart = ['labels' => [], 'gross_revenue' => [], 'refunds' => [], 'disputes' => [], 'claim_costs' => [], 'estimated_net' => [], 'successful_payments' => [], 'failed_payments' => []];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $row = $rows->get($month->format('Y-m'));
            $gross = (float) ($row->gross_revenue ?? 0);
            $refunds = (float) ($row->refunds ?? 0);
            $disputes = (float) ($row->disputes ?? 0);
            $claimCosts = (float) ($row->claim_costs ?? 0);

            $chart['labels'][] = $month->format('M Y');
            $chart['gross_revenue'][] = round($gross, 2);
            $chart['refunds'][] = round($refunds, 2);
            $chart['disputes'][] = round($disputes, 2);
            $chart['claim_costs'][] = round($claimCosts, 2);
            $chart['estimated_net'][] = round($gross - $refunds - $disputes - $claimCosts, 2);
            $chart['successful_payments'][] = (int) ($row->successful_payments ?? 0);
            $chart['failed_payments'][] = (int) ($row->failed_payments ?? 0);
        }

        return $chart;
    }

    public function operationalChart(): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $monthStart = Carbon::now()->startOfMonth();

        $newByMonth = Client::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw($this->monthKey('created_at').' as period, COUNT(*) as total')
            ->groupByRaw($this->monthKey('created_at'))
            ->pluck('total', 'period');

        $cancelledByMonth = DB::table('payment_histories')
            ->whereBetween('updated_at', [$start, $end])
            ->where('cancle_subscription', 1)
            ->selectRaw($this->monthKey('updated_at').' as period, COUNT(DISTINCT client_id) as total')
            ->groupByRaw($this->monthKey('updated_at'))
            ->pluck('total', 'period');

        $trend = ['labels' => [], 'new_customers' => [], 'cancelled_customers' => []];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $p = $month->format('Y-m');
            $trend['labels'][] = $month->format('M Y');
            $trend['new_customers'][] = (int) ($newByMonth[$p] ?? 0);
            $trend['cancelled_customers'][] = (int) ($cancelledByMonth[$p] ?? 0);
        }

        $claimStatusLabels = [
            1 => 'Attention', 2 => 'Not Scheduled', 3 => 'Scheduled', 4 => 'Complete', 5 => 'Parts Orders',
            6 => 'Not Covered', 7 => 'Claim Canceled', 8 => 'Review', 9 => 'Reopen', 10 => 'Partially Covered',
        ];
        $claimCounts = Claim::query()
            ->selectRaw('claim_status, COUNT(*) as total')
            ->whereIn('claim_status', array_keys($claimStatusLabels))
            ->groupBy('claim_status')
            ->pluck('total', 'claim_status');

        $salesmen = Client::query()
            ->whereBetween('created_at', [$monthStart, $end])
            ->where('status', '!=', 5)
            ->whereNotNull('salesman_name')->where('salesman_name', '!=', '')
            ->selectRaw('TRIM(salesman_name) as salesman, COUNT(*) as total')
            ->groupByRaw('TRIM(salesman_name)')
            ->orderByDesc('total')->limit(8)->get();

        $lead = LeadDetail::query()
            ->whereBetween('created_at', [$monthStart, $end])
            ->selectRaw('COUNT(*) as leads_received')
            ->selectRaw('SUM(CASE WHEN status != 0 THEN 1 ELSE 0 END) as leads_statused')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as sale_status')
            ->first();
        $customersCreated = Client::whereBetween('created_at', [$monthStart, $end])->where('status', '!=', 5)->count();

        return [
            'customer_trend' => $trend,
            'claim_status' => [
                'labels' => array_values($claimStatusLabels),
                'values' => array_map(fn ($s) => (int) ($claimCounts[$s] ?? 0), array_keys($claimStatusLabels)),
            ],
            'salesman_performance' => [
                'period' => $monthStart->format('F Y'),
                'labels' => $salesmen->pluck('salesman')->values()->all(),
                'values' => $salesmen->pluck('total')->map(fn ($v) => (int) $v)->values()->all(),
            ],
            'lead_funnel' => [
                'period' => $monthStart->format('F Y'),
                'labels' => ['Leads Received', 'Leads Statused', 'Sale Status', 'Customers Created'],
                'values' => [
                    (int) ($lead->leads_received ?? 0),
                    (int) ($lead->leads_statused ?? 0),
                    (int) ($lead->sale_status ?? 0),
                    $customersCreated,
                ],
            ],
        ];
    }

    protected function cancelledPolicyCount(): int
    {
        $base = fn () => DB::table('clients')->join('payment_histories', 'clients.id', '=', 'payment_histories.client_id')
            ->where('payment_histories.cancle_subscription', 1);

        return (clone $base())->where('clients.payment_structure', 1)->distinct()->count('clients.id')
            + (clone $base())->where('clients.payment_structure', 2)->whereNotNull('clients.installment')->distinct()->count('clients.id')
            + (clone $base())->where('clients.payment_structure', 2)->whereNull('clients.installment')->distinct()->count('clients.id');
    }

    protected function expectedEarnings(): float
    {
        return (float) Cache::remember('dashboard_expected_earnings_v1', now()->addMinutes(10), function () {
            $total = 0.0;

            Client::query()->select('id', 'installment', 'discount', 'price_cal_response')->orderBy('id')
                ->chunkById(500, function ($clients) use (&$total) {
                    foreach ($clients as $c) {
                        $p = json_decode((string) $c->price_cal_response);
                        $num = fn ($v) => (float) str_replace(',', '', (string) ($v ?? 0));

                        if (in_array((int) $c->installment, [2, 3, 4, 5], true)) {
                            $total += $num($p->installment_price ?? 0);
                        } elseif ((float) $c->discount > 0) {
                            $total += $num($p->discount_price ?? 0);
                        } else {
                            $total += $num($p->price ?? 0);
                        }
                    }
                });

            return $total;
        });
    }

    /** Driver-aware `YYYY-MM` key for a datetime column. */
    protected function monthKey(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', $column)"
            : "DATE_FORMAT($column, '%Y-%m')";
    }
}
