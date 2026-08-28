<?php

namespace App\Services\Earnings;

use App\Models\Claim;
use App\Models\Client;
use App\Models\ClientEarning;
use App\Models\PaymentHistory;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes `client_earnings.total_earn` / `total_profit` for every client.
 * Ported verbatim from the legacy `CalculateClientEarnings` command:
 *
 *   total_earn   = Σ (amount - refund_amount) over payment_status ∈ {1,5}
 *   plan_total   = same, but claim_payment = 0 only
 *   loss_total   = Σ amount over disputed = 1 AND loss = 1
 *   claims_total = Σ claims.total_price
 *   total_profit = (claims_total < 0 ? plan_total + |claims_total|
 *                                    : plan_total - claims_total) - loss_total
 */
class ClientEarningsCalculator
{
    /** @return int number of clients processed */
    public function recalculateAll(): int
    {
        $count = 0;

        Client::query()->select('id')->chunkById(500, function ($clients) use (&$count) {
            foreach ($clients as $client) {
                $this->recalculateFor($client->id);
                $count++;
            }
        });

        return $count;
    }

    public function recalculateFor(int $clientId): ClientEarning
    {
        $paidExpr = DB::raw('amount - IFNULL(refund_amount, 0)');

        $planTotal = (float) PaymentHistory::where('client_id', $clientId)
            ->whereIn('payment_status', [1, 5])
            ->where('claim_payment', 0)
            ->sum($paidExpr);

        $totalInvoices = (float) PaymentHistory::where('client_id', $clientId)
            ->whereIn('payment_status', [1, 5])
            ->sum($paidExpr);

        $lossTotal = (float) PaymentHistory::where('client_id', $clientId)
            ->where('disputed', 1)
            ->where('loss', 1)
            ->sum('amount');

        $claimsTotal = (float) Claim::where('client_id', $clientId)->sum('total_price');

        $profit = $claimsTotal < 0
            ? $planTotal + abs($claimsTotal)
            : $planTotal - $claimsTotal;

        $profit -= $lossTotal;

        return ClientEarning::updateOrCreate(
            ['client_id' => $clientId],
            [
                'total_earn' => round($totalInvoices, 2),
                'total_profit' => round($profit, 2),
            ],
        );
    }
}
