<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Models\PaymentHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The `payment_histories` seed-finding + backfill logic from the legacy
 * `TransactionCheckController`, ported verbatim. Used by the suspend /
 * terminated notify handlers to mark a subscription's billing as failed and
 * fill in any skipped monthly rows.
 */
class AnetPaymentHistoryResolver
{
    /** @return Builder<PaymentHistory> */
    public function seedQuery(string $subscriptionId): Builder
    {
        return PaymentHistory::query()
            ->where('subscription_id', $subscriptionId)
            ->where('claim_payment', 0)
            ->where('is_custom', 0);
    }

    public function syncAccount(PaymentHistory $paymentH, int $authorizeAccountId, bool $shouldSync = true): PaymentHistory
    {
        if ($shouldSync && (int) ($paymentH->authorize_account_id ?? 0) !== $authorizeAccountId) {
            $paymentH->authorize_account_id = $authorizeAccountId;
            $paymentH->save();
        }

        return $paymentH;
    }

    public function findSubscriptionPaymentHistory(
        string $subscriptionId,
        AuthorizeAccount $acc,
        ?int $paymentType = null,
        bool $claimMissingAccount = true,
    ): ?PaymentHistory {
        $paymentTypeFilters = $paymentType === null ? [null] : [$paymentType, null];

        foreach ($paymentTypeFilters as $filterPaymentType) {
            $baseQuery = $this->seedQuery($subscriptionId)
                ->when($filterPaymentType !== null, fn (Builder $q) => $q->where('payment_type', $filterPaymentType));

            $sameAccountRow = (clone $baseQuery)->where('authorize_account_id', $acc->id)->latest()->first();
            if ($sameAccountRow) {
                return $sameAccountRow;
            }

            $missingAccountRow = (clone $baseQuery)
                ->where(fn (Builder $q) => $q->whereNull('authorize_account_id')->orWhere('authorize_account_id', 0))
                ->latest()->first();
            if ($missingAccountRow) {
                return $this->syncAccount($missingAccountRow, $acc->id, $claimMissingAccount);
            }

            $mismatchedAccountRow = (clone $baseQuery)->latest()->first();
            if ($mismatchedAccountRow) {
                return $this->syncAccount($mismatchedAccountRow, $acc->id, $claimMissingAccount);
            }
        }

        return null;
    }

    public function expectedRecurringAmount(?Client $client, ?PaymentHistory $fallback = null): float
    {
        if (! $client) {
            return round((float) ($fallback->amount ?? 0), 2);
        }

        $priceData = json_decode((string) ($client->price_cal_response ?? ''), true);
        $priceData = is_array($priceData) ? $priceData : [];

        $toFloat = static fn ($v): float => round((float) str_replace(',', '', (string) $v), 2);

        foreach ([
            $toFloat($client->custom_price ?? 0),
            $toFloat($priceData['discount_price'] ?? 0),
            $toFloat($priceData['installment_price'] ?? 0),
            $toFloat($priceData['price'] ?? 0),
        ] as $candidate) {
            if ($candidate > 0) {
                return $candidate;
            }
        }

        return round((float) ($fallback->amount ?? 0), 2);
    }

    public function normalizePaymentDateTime(mixed $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return now()->format('Y-m-d H:i:s');
        }
    }

    public function replicateRow(
        PaymentHistory $paymentH,
        string $paymentDateTime,
        ?string $transactionId,
        int $paymentStatus,
        float $amount,
        ?int $authorizeAccountId = null,
    ): PaymentHistory {
        $clone = $paymentH->replicate();
        $clone->payment_date = $paymentDateTime;
        $clone->transaction_id = $transactionId;
        $clone->payment_s_date = null;
        $clone->status = $paymentStatus;
        $clone->payment_status = $paymentStatus;
        $clone->amount = $amount;
        $clone->claim_payment = 0;
        $clone->refund_claim = 0;
        $clone->refund_payment = 0;
        $clone->refund_date = null;
        $clone->claim_id = null;
        $clone->authorize_account_id = $authorizeAccountId ?? $paymentH->authorize_account_id;
        $clone->save();

        return $clone;
    }

    public function paymentAttemptExistsForSeed(
        PaymentHistory $paymentH,
        string $paymentDateTime,
        int $paymentStatus,
        float $amount,
        ?int $paymentType,
        int $authorizeAccountId,
    ): bool {
        $paymentDateTime = Carbon::parse($paymentDateTime)->format('Y-m-d H:i:s');
        $normalizedAmount = round($amount, 2);

        return PaymentHistory::query()
            ->where('client_id', $paymentH->client_id)
            ->where('subscription_id', $paymentH->subscription_id)
            ->where('claim_payment', 0)
            ->where('is_custom', 0)
            ->where('payment_status', $paymentStatus)
            ->where('status', $paymentStatus)
            ->where('payment_date', $paymentDateTime)
            ->where('authorize_account_id', $authorizeAccountId)
            ->whereRaw('ABS(amount - ?) < 0.01', [$normalizedAmount])
            ->when($paymentType !== null, fn (Builder $q) => $q->where('payment_type', $paymentType))
            ->exists();
    }

    public function createPaymentAttemptRowIfMissing(
        PaymentHistory $paymentH,
        AuthorizeAccount $acc,
        string $paymentDateTime,
        ?string $transactionId,
        int $paymentStatus,
        float $amount,
        ?int $paymentType = null,
    ): bool {
        if ($this->paymentAttemptExistsForSeed($paymentH, $paymentDateTime, $paymentStatus, $amount, $paymentType, $acc->id)) {
            return false;
        }

        $this->replicateRow($paymentH, $paymentDateTime, $transactionId, $paymentStatus, $amount, $acc->id);

        return true;
    }

    /**
     * Fill in `payment_histories` rows for any monthly cycle that was skipped
     * between the last recorded payment and the current event. Ported verbatim
     * (35-day gap tolerance, month-boundary walk, per-month duplicate guard).
     */
    public function createMissingMonthlyPayments(
        Client $client,
        PaymentHistory $lastPayment,
        Carbon $currentPaymentDate,
        float $amount,
        ?int $authorizeAccountId = null,
    ): void {
        $paymentStructure = (string) $client->payment_structure;

        $isEligible = $paymentStructure === '1'
            || ($paymentStructure === '2' && ! is_null($client->installment));

        if (! $isEligible || empty($lastPayment->payment_date)) {
            return;
        }

        $aid = $authorizeAccountId ?? $lastPayment->authorize_account_id;

        try {
            $lastDate = Carbon::parse($lastPayment->payment_date);
        } catch (\Throwable $e) {
            return;
        }

        $current = $currentPaymentDate->copy();

        if ($lastDate->diffInDays($current) <= (int) config('billing.anet.monthly_gap_days', 35)) {
            return;
        }

        $stopAt = $current->copy()->startOfMonth()->startOfDay();
        $nextExpected = $lastDate->copy()->addMonthNoOverflow()->startOfDay();

        while ($nextExpected->lt($stopAt)) {
            // Per-month duplicate guard. The legacy code used
            // DATE_FORMAT(payment_date,'%Y-%m') = 'Y-m'; a lexicographic range
            // on the 'Y-m-d H:i:s' string is equivalent and portable.
            $monthStart = $nextExpected->format('Y-m').'-01 00:00:00';
            $monthEnd = $nextExpected->copy()->addMonthNoOverflow()->format('Y-m').'-01 00:00:00';

            $existing = PaymentHistory::where('subscription_id', $lastPayment->subscription_id)
                ->where('claim_payment', 0)
                ->where('is_custom', 0)
                ->where('payment_type', $lastPayment->payment_type)
                ->where('payment_date', '>=', $monthStart)
                ->where('payment_date', '<', $monthEnd)
                ->latest()
                ->first();

            if ($existing) {
                $this->syncAccount($existing, (int) $aid);
            } else {
                $this->replicateRow($lastPayment, $nextExpected->format('Y-m-d H:i:s'), null, 0, $amount, $aid);
            }

            $nextExpected->addMonthNoOverflow();
        }
    }
}
