<?php

namespace App\Services\Reporting;

use App\Models\Claim;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\SaleCommission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finance Cockpit aggregates. Ported from the legacy
 * FinanceCockpit\FinanceCockpitController (buildSummary / monthlySeries /
 * topSalesmen / topClients). Same formulas; the month key is driver-aware.
 */
class FinanceCockpitService
{
    /** @return array{from:Carbon,to:Carbon,months:int} */
    public function resolveRange(int $months): array
    {
        $months = max(1, min(24, $months));

        return [
            'from' => Carbon::now()->subMonths($months - 1)->startOfMonth(),
            'to' => Carbon::now()->endOfMonth(),
            'months' => $months,
        ];
    }

    public function summary(Carbon $from, Carbon $to): array
    {
        $t = PaymentHistory::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 AND refund_payment = 0 THEN amount ELSE 0 END) as gross_revenue')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND refund_payment = 1 THEN COALESCE(NULLIF(refund_amount, 0), amount) ELSE 0 END) as refunds')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND disputed = 1 THEN amount ELSE 0 END) as disputes')
            ->selectRaw('SUM(CASE WHEN claim_payment = 1 AND payment_status = 1 AND refund_claim = 0 THEN amount ELSE 0 END) as claim_costs')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 THEN 1 ELSE 0 END) as successful_payments')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 0 THEN 1 ELSE 0 END) as failed_payments')
            ->first();

        $commissionExpense = (float) SaleCommission::query()
            ->whereBetween('created_at', [$from, $to])->sum('commision_price');

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
            'commission_expense' => $commissionExpense,
            'estimated_net' => $gross - $refunds - $disputes - $claimCosts - $commissionExpense,
            'new_customers' => Client::whereBetween('created_at', [$from, $to])->count(),
            'claims_opened' => Claim::whereBetween('created_at', [$from, $to])->count(),
            'successful_payments' => $ok,
            'failed_payments' => $failed,
            'payment_attempts' => $ok + $failed,
        ];
    }

    public function monthlySeries(int $months = 12): array
    {
        $months = max(3, min(24, $months));
        $start = Carbon::now()->subMonths($months - 1)->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $key = $this->monthKey('created_at');

        $payments = PaymentHistory::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("$key as period")
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 AND refund_payment = 0 THEN amount ELSE 0 END) as gross_revenue')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND refund_payment = 1 THEN COALESCE(NULLIF(refund_amount, 0), amount) ELSE 0 END) as refunds')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND disputed = 1 THEN amount ELSE 0 END) as disputes')
            ->selectRaw('SUM(CASE WHEN claim_payment = 1 AND payment_status = 1 AND refund_claim = 0 THEN amount ELSE 0 END) as claim_costs')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 THEN 1 ELSE 0 END) as successful_payments')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND payment_status = 0 THEN 1 ELSE 0 END) as failed_payments')
            ->groupByRaw($key)->get()->keyBy('period');

        $commissions = SaleCommission::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("$key as period")
            ->selectRaw('SUM(commision_price) as commission_expense')
            ->groupByRaw($key)->get()->keyBy('period');

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $p = $month->format('Y-m');
            $pr = $payments->get($p);
            $gross = (float) ($pr->gross_revenue ?? 0);
            $refunds = (float) ($pr->refunds ?? 0);
            $disputes = (float) ($pr->disputes ?? 0);
            $claimCosts = (float) ($pr->claim_costs ?? 0);
            $commission = (float) ($commissions->get($p)->commission_expense ?? 0);

            $series[] = [
                'period' => $p,
                'label' => $month->format('M Y'),
                'gross_revenue' => round($gross, 2),
                'refunds' => round($refunds, 2),
                'disputes' => round($disputes, 2),
                'claim_costs' => round($claimCosts, 2),
                'commission_expense' => round($commission, 2),
                'estimated_net' => round($gross - $refunds - $disputes - $claimCosts - $commission, 2),
                'successful_payments' => (int) ($pr->successful_payments ?? 0),
                'failed_payments' => (int) ($pr->failed_payments ?? 0),
            ];
        }

        return $series;
    }

    public function topSalesmen(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        $salesCounts = Client::query()
            ->selectRaw('LOWER(TRIM(salesman_name)) as salesman_key, COUNT(*) as sales_count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('salesman_key')->pluck('sales_count', 'salesman_key');

        $commissionTotals = SaleCommission::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('user_id, SUM(commision_price) as commission_total')
            ->groupBy('user_id')->pluck('commission_total', 'user_id');

        $billedTotals = DB::table('clients')
            ->join('payment_histories', 'clients.id', '=', 'payment_histories.client_id')
            ->whereBetween('payment_histories.created_at', [$from, $to])
            ->where('payment_histories.claim_payment', 0)
            ->where('payment_histories.payment_status', 1)
            ->selectRaw('LOWER(TRIM(clients.salesman_name)) as salesman_key, SUM(payment_histories.amount) as billed_total')
            ->groupBy('salesman_key')->pluck('billed_total', 'salesman_key');

        return User::query()
            ->where('status', 1)->whereNotNull('role_id')
            ->get(['id', 'first_name', 'last_name'])
            ->map(function (User $u) use ($salesCounts, $commissionTotals, $billedTotals) {
                $key = strtolower(trim((string) $u->first_name));
                $u->sales_count = (int) ($salesCounts[$key] ?? 0);
                $u->commission_total = (float) ($commissionTotals[$u->id] ?? 0);
                $u->billed_total = (float) ($billedTotals[$key] ?? 0);

                return $u;
            })
            ->sortByDesc('commission_total')
            ->take($limit)
            ->values();
    }

    public function topClients(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return PaymentHistory::query()
            ->with('userInfo:id,first_name,last_name,email,policy_number,status,authorize_status')
            ->selectRaw('client_id, SUM(CASE WHEN claim_payment = 0 AND payment_status = 1 AND refund_payment = 0 THEN amount ELSE 0 END) as revenue_total')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND refund_payment = 1 THEN COALESCE(NULLIF(refund_amount, 0), amount) ELSE 0 END) as refund_total')
            ->selectRaw('SUM(CASE WHEN claim_payment = 0 AND disputed = 1 THEN amount ELSE 0 END) as dispute_total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('client_id')
            ->orderByDesc('revenue_total')
            ->limit($limit)
            ->get();
    }

    public function ledgerRows(array $summary): array
    {
        return [
            ['label' => 'Gross Revenue', 'amount' => $summary['gross_revenue']],
            ['label' => 'Refunds', 'amount' => $summary['refunds'] * -1],
            ['label' => 'Disputes', 'amount' => $summary['disputes'] * -1],
            ['label' => 'Claim Cost', 'amount' => $summary['claim_costs'] * -1],
            ['label' => 'Sales Commission', 'amount' => $summary['commission_expense'] * -1],
            ['label' => 'Estimated Net', 'amount' => $summary['estimated_net']],
        ];
    }

    protected function monthKey(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', $column)"
            : "DATE_FORMAT($column, '%Y-%m')";
    }
}
