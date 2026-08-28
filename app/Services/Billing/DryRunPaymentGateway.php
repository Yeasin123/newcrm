<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default gateway. Moves no money and makes no HTTP call — it returns
 * plausible synthetic identifiers and logs the intent, so the whole checkout
 * / customer-action layer can be exercised end-to-end in dev, test, and on
 * production until BOTH billing flags are deliberately flipped to 'live'.
 */
class DryRunPaymentGateway implements PaymentGateway
{
    public function isLive(): bool
    {
        return false;
    }

    public function createCardProfile(AuthorizeAccount $account, Client $client, string $cardNumber, string $expMonth, string $expYear): array
    {
        $this->log('createCardProfile', ['account_id' => $account->id, 'client_id' => $client->id]);
        $digits = preg_replace('/\D+/', '', $cardNumber);

        return [
            'customer_profile_id' => 'DRYRUN-CP-'.Str::upper(Str::random(8)),
            'payment_profile_id' => 'DRYRUN-PP-'.Str::upper(Str::random(8)),
            'brand' => 'Card',
            'last4' => strlen((string) $digits) >= 4 ? substr($digits, -4) : null,
        ];
    }

    public function createOpaqueProfile(AuthorizeAccount $account, array $billTo, string $dataDescriptor, string $dataValue): array
    {
        $this->log('createOpaqueProfile', ['account_id' => $account->id]);

        return [
            'customer_profile_id' => 'DRYRUN-CP-'.Str::upper(Str::random(8)),
            'payment_profile_id' => 'DRYRUN-PP-'.Str::upper(Str::random(8)),
        ];
    }

    public function chargeProfile(AuthorizeAccount $account, string $customerProfileId, string $paymentProfileId, float $amount, ?string $description = null): array
    {
        $this->log('chargeProfile', ['account_id' => $account->id, 'amount' => $amount, 'description' => $description]);

        return ['transaction_id' => 'DRYRUN-TXN-'.Str::upper(Str::random(10)), 'auth_code' => 'DRYRUN'];
    }

    public function chargeCard(AuthorizeAccount $account, Client $client, string $cardNumber, string $expMonth, string $expYear, float $amount): array
    {
        $this->log('chargeCard', ['account_id' => $account->id, 'client_id' => $client->id, 'amount' => $amount]);

        return ['transaction_id' => 'DRYRUN-TXN-'.Str::upper(Str::random(10))];
    }

    public function chargeBank(AuthorizeAccount $account, Client $client, array $bank, float $amount): array
    {
        $this->log('chargeBank', ['account_id' => $account->id, 'client_id' => $client->id, 'amount' => $amount]);

        return ['transaction_id' => 'DRYRUN-TXN-'.Str::upper(Str::random(10))];
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
        $this->log('createSubscriptionFromProfile', [
            'account_id' => $account->id, 'amount' => $amount,
            'interval' => "{$intervalLength} {$intervalUnit}", 'occurrences' => $totalOccurrences,
            'start' => $startDate->toDateString(),
        ]);

        return 'DRYRUN-SUB-'.Str::upper(Str::random(8));
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
        $this->log('createSubscriptionFromBank', [
            'account_id' => $account->id, 'client_id' => $client->id, 'amount' => $amount,
            'interval' => "{$intervalLength} {$intervalUnit}", 'occurrences' => $totalOccurrences,
        ]);

        return 'DRYRUN-SUB-'.Str::upper(Str::random(8));
    }

    public function refundPayment(AuthorizeAccount $account, string $refTransId, float $amount, string $cardLast4): array
    {
        $this->log('refundPayment', ['account_id' => $account->id, 'ref' => $refTransId, 'amount' => $amount]);

        return ['transaction_id' => 'DRYRUN-RFND-'.Str::upper(Str::random(10))];
    }

    public function voidPayment(AuthorizeAccount $account, string $refTransId): array
    {
        $this->log('voidPayment', ['account_id' => $account->id, 'ref' => $refTransId]);

        return ['transaction_id' => 'DRYRUN-VOID-'.Str::upper(Str::random(10))];
    }

    public function cancelSubscription(AuthorizeAccount $account, string $subscriptionId): string
    {
        $this->log('cancelSubscription', ['account_id' => $account->id, 'subscription_id' => $subscriptionId]);

        return 'ok';
    }

    public function updateSubscriptionAmount(AuthorizeAccount $account, string $subscriptionId, float $amount): void
    {
        $this->log('updateSubscriptionAmount', ['account_id' => $account->id, 'subscription_id' => $subscriptionId, 'amount' => $amount]);
    }

    private function log(string $op, array $context): void
    {
        Log::info("[billing:dry-run] {$op}", $context);
    }
}
