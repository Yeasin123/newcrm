<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentRetryAttemptResult;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\Anet\TransactionGateway;
use App\Services\FailedPayment\Contracts\FailedPaymentRetryGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * Live CIM retry. Ported from the legacy gateway — same context resolution
 * (client + all unpaid payment_histories → sum amount, saved CardProfile,
 * owning merchant account) and the same exception classification — but the
 * charge itself goes through the Phase 6c TransactionGateway (JSON API)
 * instead of the abandoned SDK.
 *
 * Only bound when the two-flag gate opens
 * (retry_gateway_driver = live_cim AND retry_processing_mode = live AND
 * dry_run_mode = false).
 */
class LiveFailedPaymentRetryGateway implements FailedPaymentRetryGatewayInterface
{
    public function __construct(private readonly TransactionGateway $txn) {}

    public function retry(array $request): FailedPaymentRetryResult
    {
        try {
            $context = $this->resolveContext($request);

            if ($context === null) {
                return new FailedPaymentRetryResult(
                    attemptResult: FailedPaymentRetryAttemptResult::BLOCKED,
                    scheduleStatus: FailedPaymentRetryScheduleStatus::PENDING,
                    safeMessage: 'Live retry is missing the saved payment profile or authorize account reference.',
                    metadata: ['gateway_driver' => 'live_cim'],
                );
            }

            $result = $this->txn->chargeCustomerProfile(
                $context['account'],
                (string) $context['customer_profile_id'],
                (string) $context['payment_profile_id'],
                (float) $context['amount'],
                $context['transaction_ref'],
            );

            return new FailedPaymentRetryResult(
                attemptResult: FailedPaymentRetryAttemptResult::SUCCESS,
                scheduleStatus: FailedPaymentRetryScheduleStatus::COMPLETED,
                gatewayReference: $result['transaction_id'],
                safeMessage: 'Live retry completed successfully.',
                metadata: [
                    'gateway_driver' => 'live_cim',
                    'authorize_account_id' => $context['authorize_account_id'],
                    'client_id' => $context['client_id'],
                    'payment_history_id' => $context['payment_history_id'],
                    'payment_history_ids' => $context['payment_history_ids'],
                    'resolved_payment_history_id' => $context['resolved_payment_history_id'],
                    'customer_profile_id' => $context['customer_profile_id'],
                    'payment_profile_id' => $context['payment_profile_id'],
                    'charge_amount' => $context['amount'],
                    'primary_amount' => $context['primary_amount'],
                    'transaction_id' => $result['transaction_id'],
                ],
            );
        } catch (\Throwable $e) {
            $classification = $this->classifyException($e);

            Log::warning('Failed-payment live retry failed.', [
                'error' => $e->getMessage(),
                'classification' => $classification['attempt_result'],
                'gateway_driver' => 'live_cim',
                'client_id' => $request['client_id'] ?? null,
                'payment_history_id' => $request['payment_history_id'] ?? null,
                'authorize_account_id' => $request['authorize_account_id'] ?? null,
            ]);

            return new FailedPaymentRetryResult(
                attemptResult: $classification['attempt_result'],
                scheduleStatus: $classification['schedule_status'],
                gatewayReference: null,
                safeMessage: $classification['safe_message'],
                metadata: [
                    'gateway_driver' => 'live_cim',
                    'error' => $this->safeText($e->getMessage()),
                ],
            );
        }
    }

    protected function resolveContext(array $request): ?array
    {
        $clientId = (int) ($request['client_id'] ?? 0);
        $paymentHistoryId = (int) ($request['payment_history_id'] ?? 0);

        if ($clientId <= 0) {
            return null;
        }

        $client = Client::query()->find($clientId);
        if (! $client) {
            return null;
        }

        $unpaidPaymentHistories = PaymentHistory::query()
            ->where('client_id', $clientId)
            ->where('payment_status', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($unpaidPaymentHistories->isEmpty()) {
            return null;
        }

        $paymentHistory = ($paymentHistoryId > 0 ? $unpaidPaymentHistories->firstWhere('id', $paymentHistoryId) : null)
            ?: $unpaidPaymentHistories->first();

        if (! $paymentHistory) {
            return null;
        }

        $cardProfile = $this->resolveCardProfile($clientId, $request, $paymentHistory);
        if (! $cardProfile) {
            return null;
        }

        $authorizeAccountId = (int) ($request['authorize_account_id'] ?? $cardProfile->authorize_account_id ?? $client->authorize_account_id ?? 0);
        if ($authorizeAccountId <= 0) {
            return null;
        }

        $account = AuthorizeAccount::query()
            ->whereKey($authorizeAccountId)
            ->where(fn ($q) => $q->whereNull('is_disabled')->orWhere('is_disabled', 0))
            ->first();

        if (! $account) {
            return null;
        }

        $amount = round((float) $unpaidPaymentHistories->sum('amount'), 2);
        if ($amount <= 0) {
            return null;
        }

        $customerProfileId = (string) ($request['customer_profile_id'] ?? $cardProfile->customer_profile_id ?? '');
        $paymentProfileId = (string) ($request['payment_profile_id'] ?? $cardProfile->payment_profile_id ?? '');

        if ($customerProfileId === '' || $paymentProfileId === '') {
            return null;
        }

        return [
            'client_id' => $clientId,
            'payment_history_id' => (int) $paymentHistory->id,
            'payment_history_ids' => $unpaidPaymentHistories->pluck('id')->values()->all(),
            'resolved_payment_history_id' => (int) $paymentHistory->id,
            'authorize_account_id' => (int) $account->id,
            'account' => $account,
            'client' => $client,
            'card_profile' => $cardProfile,
            'amount' => round($amount, 2),
            'primary_amount' => round((float) ($paymentHistory->amount ?? 0), 2),
            'customer_profile_id' => $customerProfileId,
            'payment_profile_id' => $paymentProfileId,
            'transaction_ref' => (string) ($request['transaction_ref'] ?? ('failed-payment-retry-'.$clientId.'-'.now()->timestamp)),
        ];
    }

    protected function resolveCardProfile(int $clientId, array $request, PaymentHistory $paymentHistory): ?CardProfile
    {
        $customerProfileId = trim((string) ($request['customer_profile_id'] ?? ''));
        $paymentProfileId = trim((string) ($request['payment_profile_id'] ?? ''));

        if ($customerProfileId !== '' && $paymentProfileId !== '') {
            $cardProfile = CardProfile::query()
                ->where('client_id', $clientId)
                ->where('customer_profile_id', $customerProfileId)
                ->where('payment_profile_id', $paymentProfileId)
                ->first();

            if ($cardProfile) {
                return $cardProfile;
            }
        }

        $fallbackQuery = CardProfile::query()
            ->where('client_id', $clientId)
            ->whereNotNull('customer_profile_id')
            ->whereNotNull('payment_profile_id')
            ->orderByDesc('is_default')
            ->orderByDesc('id');

        if (! empty($paymentHistory->authorize_account_id)) {
            $preferred = (clone $fallbackQuery)
                ->where('authorize_account_id', $paymentHistory->authorize_account_id)
                ->first();

            if ($preferred) {
                return $preferred;
            }
        }

        return $fallbackQuery->first();
    }

    protected function classifyException(\Throwable $e): array
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'declin') || str_contains($message, 'expired') || str_contains($message, 'invalid')) {
            return [
                'attempt_result' => FailedPaymentRetryAttemptResult::DECLINED,
                'schedule_status' => FailedPaymentRetryScheduleStatus::FAILED,
                'safe_message' => 'Live retry was declined by Authorize.Net.',
            ];
        }

        if (str_contains($message, 'duplicate') || str_contains($message, 'already')) {
            return [
                'attempt_result' => FailedPaymentRetryAttemptResult::PERMANENT_FAILURE,
                'schedule_status' => FailedPaymentRetryScheduleStatus::FAILED,
                'safe_message' => 'Live retry was rejected because a duplicate or final state was detected.',
            ];
        }

        return [
            'attempt_result' => FailedPaymentRetryAttemptResult::TEMPORARY_FAILURE,
            'schedule_status' => FailedPaymentRetryScheduleStatus::PENDING,
            'safe_message' => 'Live retry could not be completed due to a gateway or network error.',
        ];
    }

    protected function safeText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
