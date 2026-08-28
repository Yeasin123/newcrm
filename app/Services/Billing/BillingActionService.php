<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\Anet\TransactionGateway;
use App\Support\CardBrand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ad-hoc billing actions from the customer screen: a one-off / custom charge,
 * a void-or-refund of an existing payment, and policy cancellation. Ports the
 * relevant slices of PaymentFromClientInvoiceController, PaymentRefundController
 * and the ARB cancel flow. All gateway calls go through PaymentGateway, so in
 * dry-run mode these record rows without moving money.
 */
class BillingActionService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuthorizeAccountSelector $accounts,
        private readonly TransactionGateway $txn,
    ) {}

    /**
     * A manual one-time charge against the client's saved card profile.
     * Writes an `is_custom = 1` PaymentHistory row.
     */
    public function chargeCustom(Client $client, float $amount, string $reason): PaymentHistory
    {
        $amount = round($amount, 2);
        abort_if($amount <= 0, 422, 'Amount must be positive.');

        $profile = CardProfile::where('client_id', $client->id)->latest('id')->first();

        if (! $profile) {
            throw new \RuntimeException('This customer has no saved card profile to charge.');
        }

        $account = AuthorizeAccount::find($profile->authorize_account_id)
            ?? $this->accounts->pickForCharge();

        if (! $account) {
            throw new \RuntimeException('No active Authorize.Net account is configured.');
        }

        return DB::transaction(function () use ($client, $amount, $reason, $profile, $account) {
            $result = $this->gateway->chargeProfile(
                $account,
                (string) $profile->customer_profile_id,
                (string) $profile->payment_profile_id,
                $amount,
                $reason,
            );

            $history = PaymentHistory::create([
                'plan_id' => (string) time(),
                'client_id' => $client->id,
                'authorize_account_id' => $account->id,
                'transaction_id' => $result['transaction_id'],
                'purpose' => $reason,
                'amount' => $amount,
                'payment_date' => Carbon::now(),
                'status' => 1,
                'payment_status' => 1,
                'payment_type' => 0,
                'is_custom' => 1,
                'claim_payment' => 0,
                'card_number' => $profile->last4,
            ]);

            Log::info('Custom charge recorded', [
                'client_id' => $client->id, 'amount' => $amount,
                'transaction_id' => $result['transaction_id'], 'dry_run' => ! $this->gateway->isLive(),
            ]);

            return $history;
        });
    }

    /**
     * Save a new payment instrument on file for a customer and link it to the
     * client via a `card_profiles` row.
     *
     * Card path creates an Authorize.Net CIM customer/payment profile
     * (`customer_profile_id` + `payment_profile_id`) — from an Accept.js
     * opaque token when one is supplied, or from a raw PAN only while billing
     * is in dry-run. Those two ids are what every later charge / ARB
     * subscription for this customer bills against.
     *
     * Bank path stores the eCheck fields (routing + last-4 + name); the ACH
     * charge / subscribe paths take the raw bank fields, so no CIM profile is
     * created for a bank instrument.
     */
    public function addPaymentMethod(Client $client, PaymentInput $payment): CardProfile
    {
        $account = $this->accounts->pickForCharge() ?? $this->accounts->pickForNewClient();

        if (! $account) {
            throw new \RuntimeException('No active Authorize.Net account is configured.');
        }

        $isFirst = CardProfile::where('client_id', $client->id)->doesntExist();

        if ($payment->isBank()) {
            return CardProfile::create([
                'client_id' => $client->id,
                'authorize_account_id' => $account->id,
                'routingNumber' => $payment->bank['routingNumber'] ?? null,
                'accountNumber' => substr(preg_replace('/\D+/', '', (string) ($payment->bank['accountNumber'] ?? '')), -4) ?: null,
                'bankAccountName' => $payment->bank['bankAccountName'] ?? null,
                'echeckType' => $payment->bank['echeckType'] ?? 'WEB',
                'is_default' => $isFirst ? 1 : 0,
            ]);
        }

        if ($payment->hasOpaqueToken()) {
            $ids = $this->gateway->createOpaqueProfile(
                $account,
                $this->billToFor($client),
                (string) $payment->opaqueDataDescriptor,
                (string) $payment->opaqueDataValue,
            );
            $last4 = $payment->last4();
            $brand = $payment->brand;
        } elseif (! $this->gateway->isLive() && $payment->hasRawCard()) {
            $ids = $this->gateway->createCardProfile(
                $account,
                $client,
                (string) $payment->cardNumber,
                (string) $payment->expMonth,
                (string) $payment->expYear,
            );
            $last4 = $ids['last4'] ?? $payment->last4();
            $brand = $ids['brand'] ?? null;
        } else {
            throw new \RuntimeException('The card was not tokenized — re-enter the card details and try again.');
        }

        $profile = CardProfile::create([
            'client_id' => $client->id,
            'authorize_account_id' => $account->id,
            'customer_profile_id' => $ids['customer_profile_id'],
            'payment_profile_id' => $ids['payment_profile_id'],
            'last4' => $last4,
            'brand' => $brand ?: CardBrand::detect($last4),
            'expMonth' => $payment->expMonth,
            'expYear' => $payment->expYear,
            'is_default' => $isFirst ? 1 : 0,
        ]);

        Log::info('Payment method added', [
            'client_id' => $client->id, 'card_profile_id' => $profile->id,
            'authorize_account_id' => $account->id, 'dry_run' => ! $this->gateway->isLive(),
        ]);

        return $profile;
    }

    /** @return array<string,string> */
    private function billToFor(Client $client): array
    {
        return [
            'first_name' => (string) $client->first_name,
            'last_name' => (string) $client->last_name,
            'email' => (string) $client->email,
            'address' => (string) $client->address,
            'city' => (string) $client->city,
            'zip' => (string) $client->zip_code,
        ];
    }

    /**
     * Void (same billing day, before 21:05) or refund (after) an existing
     * payment. The window rule is `PaymentHistory::can_void`, ported verbatim
     * in Phase 2; in live mode we additionally confirm settlement status.
     *
     * @return array{action:'void'|'refund', transaction_id:string}
     */
    public function voidOrRefund(PaymentHistory $payment, ?float $amount = null): array
    {
        if (blank($payment->transaction_id)) {
            throw new \RuntimeException('This payment has no gateway transaction id to reverse.');
        }

        $account = AuthorizeAccount::find($payment->authorize_account_id)
            ?? $this->accounts->bySubscriptionId((string) $payment->subscription_id)
            ?? $this->accounts->pickForCharge();

        if (! $account) {
            throw new \RuntimeException('Could not resolve the merchant account for this payment.');
        }

        $shouldVoid = (bool) $payment->can_void;

        if ($this->gateway->isLive()) {
            $details = $this->txn->getTransactionDetails($account, (string) $payment->transaction_id);
            $status = (string) data_get($details, 'transaction.transactionStatus', '');

            if (in_array($status, ['settledSuccessfully', 'refundSettledSuccessfully'], true)) {
                $shouldVoid = false;
            }
        }

        $refundAmount = round($amount ?? (float) $payment->amount, 2);

        return DB::transaction(function () use ($payment, $account, $shouldVoid, $refundAmount) {
            if ($shouldVoid) {
                $result = $this->gateway->voidPayment($account, (string) $payment->transaction_id);
                $action = 'void';
                $payment->refund_amount = (float) $payment->amount; // a void reverses the whole charge
            } else {
                $last4 = (string) ($payment->card_number ?: '0000');
                $result = $this->gateway->refundPayment($account, (string) $payment->transaction_id, $refundAmount, $last4);
                $action = 'refund';
                $payment->refund_amount = $refundAmount;
            }

            $payment->refund_payment = 1;
            $payment->refund_date = Carbon::now()->toDateString();
            $payment->refund_transaction_id = $result['transaction_id'];
            $payment->save();

            Log::info('Payment reversed', [
                'payment_history_id' => $payment->id, 'action' => $action,
                'amount' => $payment->refund_amount, 'dry_run' => ! $this->gateway->isLive(),
            ]);

            return ['action' => $action, 'transaction_id' => $result['transaction_id']];
        });
    }

    /**
     * Cancel the policy: cancel the ARB subscription on the gateway and take
     * the customer offline (status 0, portal login blocked, cancel date set).
     *
     * @return 'ok'|'already_canceled'|'error'|'no_subscription'
     */
    public function cancelPolicy(Client $client, ?string $reason = null): string
    {
        $seed = PaymentHistory::where('client_id', $client->id)
            ->whereNotNull('subscription_id')
            ->latest('id')
            ->first();

        $subscriptionId = $seed?->subscription_id;
        $result = 'no_subscription';

        if ($subscriptionId) {
            $account = AuthorizeAccount::find($seed->authorize_account_id)
                ?? $this->accounts->bySubscriptionId((string) $subscriptionId)
                ?? $this->accounts->pickForCharge();

            $result = $account
                ? $this->gateway->cancelSubscription($account, (string) $subscriptionId)
                : 'error';
        }

        $client->forceFill([
            'status' => 0,
            'login_off' => 1,
            'cancle_date' => Carbon::now()->toDateString(),
        ])->save();

        Log::info('Policy cancelled', [
            'client_id' => $client->id, 'subscription_id' => $subscriptionId,
            'gateway_result' => $result, 'reason' => $reason, 'dry_run' => ! $this->gateway->isLive(),
        ]);

        return $result;
    }
}
