<?php

namespace App\Services\Billing;

use App\Models\PaymentHealthSnapshot;
use App\Models\PaymentHistory;
use App\Models\RenewalClient;
use Illuminate\Support\Carbon;

/**
 * Monthly payment-health roll-up computed live from payment_histories.
 * Mirrors the columns of `payment_health_snapshots`; persist() writes/updates
 * one row for the month.
 */
class PaymentHealthReport
{
    /** @return array<string,int|float> */
    public function forMonth(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $inMonth = fn ($q) => $q->whereBetween('created_at', [$start, $end])
            ->where('claim_payment', 0);

        $paidQ = PaymentHistory::query()->where('payment_status', 1)->tap($inMonth);
        $failedQ = PaymentHistory::query()->where('payment_status', 0)->where('is_custom', 0)->tap($inMonth);
        $refundQ = PaymentHistory::query()->where('refund_payment', 1)->tap($inMonth);
        $disputeQ = PaymentHistory::query()->where('disputed', 1)->tap($inMonth);

        $gross = (float) (clone $paidQ)->sum('amount');
        $refund = (float) (clone $refundQ)->sum('refund_amount');
        $dispute = (float) (clone $disputeQ)->where('loss', 1)->sum('amount');

        return [
            'paid_count' => (clone $paidQ)->count(),
            'failed_count' => (clone $failedQ)->count(),
            'refund_count' => (clone $refundQ)->count(),
            'dispute_count' => (clone $disputeQ)->count(),
            'renewal_count' => RenewalClient::whereBetween('created_at', [$start, $end])->count(),
            'gross_amount' => round($gross, 2),
            'failed_amount' => round((float) (clone $failedQ)->sum('amount'), 2),
            'refund_amount' => round($refund, 2),
            'dispute_amount' => round($dispute, 2),
            'net_amount' => round($gross - $refund - $dispute, 2),
        ];
    }

    public function persist(Carbon $month, ?string $notes = null): PaymentHealthSnapshot
    {
        $monthStr = $month->copy()->startOfMonth()->format('Y-m-d');

        $row = PaymentHealthSnapshot::whereDate('report_month', $monthStr)->first()
            ?? new PaymentHealthSnapshot(['report_month' => $monthStr]);

        $row->fill($this->forMonth($month) + ['notes' => $notes]);
        $row->save();

        return $row;
    }
}
