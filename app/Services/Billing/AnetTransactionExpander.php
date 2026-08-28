<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Services\Billing\Anet\TransactionGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The create-notify handler's `getSubscriptionInfo` slice, ported: given a
 * transaction id from a `payment.authcapture.created` webhook, fetch the
 * transaction from Authorize.Net, find the subscription's seed
 * payment_histories row, and record a paid attempt row for that transaction
 * if one isn't there yet.
 *
 * Only runs when `billing.anet.sdk_reads_enabled` is true (it needs a live
 * gateway read). When disabled the webhook still records the event.
 */
class AnetTransactionExpander
{
    public function __construct(
        private readonly TransactionGateway $txn,
        private readonly AnetPaymentHistoryResolver $resolver,
    ) {}

    public function expand(AuthorizeAccount $account, string $transactionId): bool
    {
        if (! config('billing.anet.sdk_reads_enabled')) {
            return false;
        }

        $details = $this->txn->getTransactionDetails($account, $transactionId);

        if (! $details) {
            Log::warning('AnetTransactionExpander: no transaction details', [
                'authorize_account_id' => $account->id, 'transaction_id' => $transactionId,
            ]);

            return false;
        }

        $txnBlock = data_get($details, 'transaction', []);
        $subscriptionId = (string) data_get($txnBlock, 'subscription.id', '');
        $amount = (float) data_get($txnBlock, 'authAmount', data_get($txnBlock, 'settleAmount', 0));
        $submitted = data_get($txnBlock, 'submitTimeUTC') ?? data_get($txnBlock, 'submitTimeLocal');
        $status = (string) data_get($txnBlock, 'transactionStatus', '');

        if ($subscriptionId === '') {
            // Not a subscription charge — nothing to expand.
            return false;
        }

        $seed = $this->resolver->findSubscriptionPaymentHistory($subscriptionId, $account);

        if (! $seed) {
            Log::warning('AnetTransactionExpander: no seed payment_history row', [
                'authorize_account_id' => $account->id, 'subscription_id' => $subscriptionId,
            ]);

            return false;
        }

        $paymentDateTime = $this->resolver->normalizePaymentDateTime($submitted ?: Carbon::now());
        $paid = in_array($status, ['capturedPendingSettlement', 'settledSuccessfully', 'authorizedPendingCapture'], true) ? 1 : 0;

        return $this->resolver->createPaymentAttemptRowIfMissing(
            $seed, $account, $paymentDateTime, $transactionId, $paid, round($amount, 2), $seed->payment_type,
        );
    }
}
