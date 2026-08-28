<?php

namespace App\Services\Billing\Anet;

use App\Exceptions\AnetApiException;
use App\Models\AuthorizeAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Automated Recurring Billing operations. Ports `AuthorizeNetArbService` to
 * the JSON API — create (with E00040 replication-delay retry), get, status,
 * cancel (I00002 = already-canceled treated as success), amount-only update.
 */
class ArbGateway
{
    public function __construct(private readonly AnetClient $client) {}

    /**
     * @param  int  $totalOccurrences  999 = effectively open-ended (legacy default)
     * @return string subscription id
     */
    public function createSubscription(
        AuthorizeAccount $account,
        string $customerProfileId,
        string $customerPaymentProfileId,
        Carbon $startDate,
        float $amount,
        int $intervalLength = 1,
        string $intervalUnit = 'months',
        ?string $subscriptionName = null,
        int $totalOccurrences = 999,
    ): string {
        $body = $this->subscriptionBody(
            $customerProfileId, $customerPaymentProfileId, $startDate, $amount,
            $intervalLength, $intervalUnit, $subscriptionName, $totalOccurrences,
        );

        $response = $this->client->call($account, 'ARBCreateSubscriptionRequest', $body);

        return (string) data_get($response, 'subscriptionId');
    }

    /**
     * Same as createSubscription but retries on E00040 ("record not found" —
     * CIM profile replication lag). Ported from the legacy retry loop.
     */
    public function createSubscriptionWithRetry(
        AuthorizeAccount $account,
        string $customerProfileId,
        string $customerPaymentProfileId,
        Carbon $startDate,
        float $amount,
        int $intervalLength = 1,
        string $intervalUnit = 'months',
        ?string $subscriptionName = null,
        int $totalOccurrences = 999,
        int $maxAttempts = 6,
        int $initialSleepSeconds = 2,
        int $retrySleepSeconds = 3,
    ): string {
        $body = $this->subscriptionBody(
            $customerProfileId, $customerPaymentProfileId, $startDate, $amount,
            $intervalLength, $intervalUnit, $subscriptionName, $totalOccurrences,
        );

        if ($initialSleepSeconds > 0) {
            sleep($initialSleepSeconds);
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->client->call($account, 'ARBCreateSubscriptionRequest', $body);

                return (string) data_get($response, 'subscriptionId');
            } catch (AnetApiException $e) {
                if ($e->anetCode === 'E00040' && $attempt < $maxAttempts) {
                    Log::warning('ANET ARB CREATE retrying due to E00040', [
                        'attempt' => $attempt, 'max' => $maxAttempts,
                        'customer_profile_id' => $customerProfileId,
                        'payment_profile_id' => $customerPaymentProfileId,
                    ]);
                    sleep($retrySleepSeconds);

                    continue;
                }

                throw $e;
            }
        }

        throw new AnetApiException('E00040', 'ARB subscription create still failing after retries.');
    }

    /**
     * ARB subscription billed to a bank account (eCheck) with a billTo block.
     *
     * @param  array<string,string>  $bank
     * @param  array<string,string>  $billTo
     */
    public function createSubscriptionFromBank(
        AuthorizeAccount $account,
        array $bank,
        Carbon $startDate,
        float $amount,
        int $intervalLength,
        string $intervalUnit,
        int $totalOccurrences,
        array $billTo = [],
        ?string $subscriptionName = null,
    ): string {
        $body = [
            'subscription' => array_filter([
                'name' => $subscriptionName ?? 'Subscription',
                'paymentSchedule' => [
                    'interval' => ['length' => $intervalLength, 'unit' => $intervalUnit],
                    'startDate' => $startDate->toDateString(),
                    'totalOccurrences' => $totalOccurrences,
                ],
                'amount' => round($amount, 2),
                'payment' => ['bankAccount' => TransactionGateway::bankBlock($bank)],
                'billTo' => array_filter($billTo),
            ]),
        ];

        $response = $this->client->call($account, 'ARBCreateSubscriptionRequest', $body);

        return (string) data_get($response, 'subscriptionId');
    }

    /** @return array<string,mixed>|null */
    public function getSubscription(AuthorizeAccount $account, string $subscriptionId): ?array
    {
        try {
            return $this->client->call($account, 'ARBGetSubscriptionRequest', [
                'subscriptionId' => $subscriptionId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ARB getSubscription exception', [
                'authorize_acc' => $account->id, 'subscription_id' => $subscriptionId, 'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Live status string ("active"|"suspended"|"canceled"|"expired"|"terminated"), or null if unknown. */
    public function getSubscriptionStatus(AuthorizeAccount $account, string $subscriptionId): ?string
    {
        try {
            $response = $this->client->call($account, 'ARBGetSubscriptionStatusRequest', [
                'subscriptionId' => $subscriptionId,
            ]);

            $status = data_get($response, 'status');

            return $status ? strtolower((string) $status) : null;
        } catch (\Throwable $e) {
            Log::warning('ARB getSubscriptionStatus exception', [
                'authorize_acc' => $account->id, 'subscription_id' => $subscriptionId, 'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return 'ok'|'already_canceled'|'error'
     */
    public function cancelSubscription(AuthorizeAccount $account, string $subscriptionId): string
    {
        try {
            $response = $this->client->call($account, 'ARBCancelSubscriptionRequest', [
                'subscriptionId' => $subscriptionId,
            ]);

            $code = (string) data_get($response, 'messages.message.0.code', '');

            if ($code === 'I00002') {
                Log::info('ARB cancel: already canceled', ['authorize_acc' => $account->id, 'subscription_id' => $subscriptionId]);

                return 'already_canceled';
            }

            return 'ok';
        } catch (AnetApiException $e) {
            // I00002 can also come back as an "Error" resultCode on some accounts.
            if ($e->anetCode === 'I00002') {
                return 'already_canceled';
            }

            Log::warning('ARB cancel failed', [
                'authorize_acc' => $account->id, 'subscription_id' => $subscriptionId,
                'code' => $e->anetCode, 'text' => $e->anetText,
            ]);

            return 'error';
        } catch (\Throwable $e) {
            Log::error('ARB cancel exception', [
                'authorize_acc' => $account->id, 'subscription_id' => $subscriptionId, 'message' => $e->getMessage(),
            ]);

            return 'error';
        }
    }

    /** Amount-only, in-place update — same subscription id keeps running. */
    public function updateSubscriptionAmount(AuthorizeAccount $account, string $subscriptionId, float $amount): void
    {
        $this->client->call($account, 'ARBUpdateSubscriptionRequest', [
            'subscriptionId' => $subscriptionId,
            'subscription' => ['amount' => round($amount, 2)],
        ]);

        Log::info('ARB update amount success', [
            'authorize_acc' => $account->id, 'subscription_id' => $subscriptionId, 'amount' => $amount,
        ]);
    }

    /** @return array<string,mixed> */
    private function subscriptionBody(
        string $customerProfileId,
        string $customerPaymentProfileId,
        Carbon $startDate,
        float $amount,
        int $intervalLength,
        string $intervalUnit,
        ?string $subscriptionName,
        int $totalOccurrences,
    ): array {
        return [
            'subscription' => [
                'name' => $subscriptionName ?? "Client Subscription {$customerProfileId}",
                'paymentSchedule' => [
                    'interval' => ['length' => $intervalLength, 'unit' => $intervalUnit],
                    'startDate' => $startDate->toDateString(),
                    'totalOccurrences' => $totalOccurrences,
                ],
                'amount' => round($amount, 2),
                'profile' => [
                    'customerProfileId' => $customerProfileId,
                    'customerPaymentProfileId' => $customerPaymentProfileId,
                ],
            ],
        ];
    }
}
