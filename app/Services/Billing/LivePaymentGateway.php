<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Services\Billing\Anet\ArbGateway;
use App\Services\Billing\Anet\CimGateway;
use App\Services\Billing\Anet\TransactionGateway;
use Illuminate\Support\Carbon;

/**
 * Real Authorize.Net implementation. Only bound when BOTH billing flags are
 * 'live'. Every method here talks to the gateway over HTTP.
 */
class LivePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly CimGateway $cim,
        private readonly ArbGateway $arb,
        private readonly TransactionGateway $txn,
    ) {}

    public function isLive(): bool
    {
        return true;
    }

    public function createCardProfile(AuthorizeAccount $account, Client $client, string $cardNumber, string $expMonth, string $expYear): array
    {
        $ids = $this->cim->createProfileFromCard($account, $client, $cardNumber, $expMonth, $expYear);
        $digits = preg_replace('/\D+/', '', $cardNumber);

        return [
            'customer_profile_id' => $ids['customer_profile_id'],
            'payment_profile_id' => $ids['payment_profile_id'],
            'brand' => $this->cim->detectBrand($cardNumber),
            'last4' => strlen((string) $digits) >= 4 ? substr($digits, -4) : null,
        ];
    }

    public function createOpaqueProfile(AuthorizeAccount $account, array $billTo, string $dataDescriptor, string $dataValue): array
    {
        return $this->cim->createProfileFromOpaque($account, $billTo, $dataDescriptor, $dataValue);
    }

    public function chargeProfile(AuthorizeAccount $account, string $customerProfileId, string $paymentProfileId, float $amount, ?string $description = null): array
    {
        $r = $this->txn->chargeCustomerProfile($account, $customerProfileId, $paymentProfileId, $amount, description: $description);

        return ['transaction_id' => $r['transaction_id'], 'auth_code' => $r['auth_code']];
    }

    public function chargeCard(AuthorizeAccount $account, Client $client, string $cardNumber, string $expMonth, string $expYear, float $amount): array
    {
        $r = $this->txn->chargeCard($account, $cardNumber, $expMonth, $expYear, $amount, self::billTo($client));

        return ['transaction_id' => $r['transaction_id']];
    }

    public function chargeBank(AuthorizeAccount $account, Client $client, array $bank, float $amount): array
    {
        $r = $this->txn->chargeBank($account, $bank, $amount, self::billTo($client));

        return ['transaction_id' => $r['transaction_id']];
    }

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
    ): string {
        return $this->arb->createSubscriptionWithRetry(
            $account, $customerProfileId, $paymentProfileId, $startDate, $amount,
            $intervalLength, $intervalUnit, $subscriptionName, $totalOccurrences,
        );
    }

    public function createSubscriptionFromBank(
        AuthorizeAccount $account,
        Client $client,
        array $bank,
        Carbon $startDate,
        float $amount,
        int $intervalLength,
        string $intervalUnit,
        int $totalOccurrences,
    ): string {
        return $this->arb->createSubscriptionFromBank(
            $account, $bank, $startDate, $amount, $intervalLength, $intervalUnit, $totalOccurrences,
            self::billTo($client), trim(($client->first_name ?? '').' Subscription'),
        );
    }

    public function refundPayment(AuthorizeAccount $account, string $refTransId, float $amount, string $cardLast4): array
    {
        return ['transaction_id' => $this->txn->refund($account, $refTransId, $amount, $cardLast4)['transaction_id']];
    }

    public function voidPayment(AuthorizeAccount $account, string $refTransId): array
    {
        return ['transaction_id' => $this->txn->void($account, $refTransId)['transaction_id']];
    }

    public function cancelSubscription(AuthorizeAccount $account, string $subscriptionId): string
    {
        return $this->arb->cancelSubscription($account, $subscriptionId);
    }

    public function updateSubscriptionAmount(AuthorizeAccount $account, string $subscriptionId, float $amount): void
    {
        $this->arb->updateSubscriptionAmount($account, $subscriptionId, $amount);
    }

    /** @return array<string,string> */
    private static function billTo(Client $client): array
    {
        return array_filter([
            'firstName' => (string) $client->first_name,
            'lastName' => (string) $client->last_name,
            'address' => (string) $client->address,
            'city' => (string) $client->city,
            'zip' => (string) $client->zip_code,
        ]);
    }
}
