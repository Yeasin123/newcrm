<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Models\Client;
use Illuminate\Support\Carbon;

/**
 * Billing primitives used by CheckoutService and the customer billing
 * actions. Two implementations, chosen by the two-flag gate in
 * config/billing.php (`billing.driver` AND `billing.processing_mode` must
 * both be 'live'):
 *
 *   - DryRunPaymentGateway (default): no HTTP, returns synthetic ids, logs.
 *   - LivePaymentGateway: real Authorize.Net JSON API calls.
 */
interface PaymentGateway
{
    public function isLive(): bool;

    /**
     * CIM profile from a raw card.
     *
     * @return array{customer_profile_id:string, payment_profile_id:string, brand:?string, last4:?string}
     */
    public function createCardProfile(AuthorizeAccount $account, Client $client, string $cardNumber, string $expMonth, string $expYear): array;

    /**
     * CIM profile from an Accept.js opaque token (raw card never seen).
     *
     * @param  array<string,string>  $billTo
     * @return array{customer_profile_id:string, payment_profile_id:string}
     */
    public function createOpaqueProfile(AuthorizeAccount $account, array $billTo, string $dataDescriptor, string $dataValue): array;

    /**
     * One-time authCapture against a stored CIM profile.
     *
     * @return array{transaction_id:string, auth_code:?string}
     */
    public function chargeProfile(AuthorizeAccount $account, string $customerProfileId, string $paymentProfileId, float $amount, ?string $description = null): array;

    /**
     * One-time authCapture against a raw card (legacy fallback path).
     *
     * @return array{transaction_id:string}
     */
    public function chargeCard(AuthorizeAccount $account, Client $client, string $cardNumber, string $expMonth, string $expYear, float $amount): array;

    /**
     * One-time authCapture against a bank account (eCheck).
     *
     * @param  array<string,string>  $bank  routingNumber,accountNumber,bankAccountName,bankAccountType,echeckType
     * @return array{transaction_id:string}
     */
    public function chargeBank(AuthorizeAccount $account, Client $client, array $bank, float $amount): array;

    /** Start an ARB subscription from a CIM profile. Returns the subscription id. */
    public function createSubscriptionFromProfile(
        AuthorizeAccount $account,
        string $customerProfileId,
        string $paymentProfileId,
        Carbon $startDate,
        float $amount,
        int $intervalLength,
        string $intervalUnit,
        int $totalOccurrences,
        ?string $subscriptionName = null,
    ): string;

    /**
     * Start an ARB subscription billed to a bank account.
     *
     * @param  array<string,string>  $bank
     */
    public function createSubscriptionFromBank(
        AuthorizeAccount $account,
        Client $client,
        array $bank,
        Carbon $startDate,
        float $amount,
        int $intervalLength,
        string $intervalUnit,
        int $totalOccurrences,
    ): string;

    /** @return array{transaction_id:string} */
    public function refundPayment(AuthorizeAccount $account, string $refTransId, float $amount, string $cardLast4): array;

    /** @return array{transaction_id:string} */
    public function voidPayment(AuthorizeAccount $account, string $refTransId): array;

    /** @return 'ok'|'already_canceled'|'error' */
    public function cancelSubscription(AuthorizeAccount $account, string $subscriptionId): string;

    public function updateSubscriptionAmount(AuthorizeAccount $account, string $subscriptionId, float $amount): void;
}
