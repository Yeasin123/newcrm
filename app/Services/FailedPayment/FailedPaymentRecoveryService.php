<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentCaseStatus;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentRetrySchedule;
use App\Models\FailedPaymentStatusHistory;
use App\Models\FailedPaymentWebhookEvent;
use App\Models\PaymentHistory;
use App\Services\Billing\Anet\ArbGateway;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FailedPaymentRecoveryService
{
    public function recoverFromSuccessfulRetry(FailedPaymentCase $case, array $context = []): array
    {
        $result = DB::transaction(function () use ($case, $context) {
            $lockedCase = FailedPaymentCase::query()->whereKey($case->id)->lockForUpdate()->first();

            if (! $lockedCase) {
                return [
                    'status' => 'unmatched',
                    'reason_code' => 'case_disappeared',
                    'case_id' => null,
                    'case_key' => null,
                    'payment_history_updated' => false,
                ];
            }

            $now = Carbon::now();
            $paymentHistoryUpdated = $this->markPaymentHistoryPaid($lockedCase, $context, $now);

            if (! in_array($lockedCase->current_status, [
                FailedPaymentCaseStatus::RECOVERED,
                FailedPaymentCaseStatus::RESOLVED,
                FailedPaymentCaseStatus::CANCELLED,
            ], true)) {
                $previousStatus = $lockedCase->current_status;
                $lockedCase->current_status = FailedPaymentCaseStatus::RECOVERED;
                $lockedCase->recovered_at = $lockedCase->recovered_at ?? $now;
                $lockedCase->resolved_at = $lockedCase->resolved_at ?? $now;
                $lockedCase->next_action_at = null;
                $lockedCase->metadata = array_replace_recursive((array) ($lockedCase->metadata ?? []), [
                    'retry_state' => 'recovered',
                    'recovery_source' => Arr::get($context, 'source', 'live_retry'),
                    'recovery_transaction_id' => Arr::get($context, 'anet_transaction_id') ?: Arr::get($context, 'gateway_reference'),
                    'last_successful_event_at' => $now->toIso8601String(),
                    'last_successful_event_transaction_id' => Arr::get($context, 'anet_transaction_id') ?: Arr::get($context, 'gateway_reference'),
                    'last_successful_event_subscription_id' => Arr::get($context, 'arb_subscription_id'),
                ]);
                $lockedCase->save();

                $this->cancelRemainingSchedules($lockedCase, $context, $now);
                $this->recordStatusHistory($lockedCase, $previousStatus, FailedPaymentCaseStatus::RECOVERED, $context, $now);
                $this->recordAuditRecovered($lockedCase, $previousStatus, $context, $now, 'Recovered from successful retry charge.');
            }

            return [
                'status' => 'recovered',
                'reason_code' => 'successful_retry',
                'case_id' => $lockedCase->id,
                'case_key' => $lockedCase->case_key,
                'payment_history_updated' => $paymentHistoryUpdated,
                'arb_subscription_create_context' => $this->buildArbSubscriptionCreateContext($lockedCase, $context),
            ];
        });

        $arbSubscriptionCreate = $this->attemptArbSubscriptionCreate($result['arb_subscription_create_context'] ?? null);

        if ($arbSubscriptionCreate !== null) {
            $result['arb_subscription_create'] = $arbSubscriptionCreate;

            if (in_array($arbSubscriptionCreate['status'] ?? null, ['created', 'already_exists'], true)) {
                $this->syncLocalClientAfterArbSubscriptionCreate((int) ($result['case_id'] ?? 0), $arbSubscriptionCreate);
            }
        }

        return $result;
    }

    public function recoverFromVerifiedSuccess(array $context): array
    {
        $context = $this->sanitizeContext($context);
        $event = $this->resolveVerifiedEvent($context);

        if (! $event || ! $this->isVerifiedSuccessEvent($event)) {
            return $this->recordUnmatched($context, 'unverified_success_event');
        }

        $evidence = $this->extractSuccessEvidence($event);
        if (! $this->hasStrongRecoveryEvidence($evidence)) {
            return $this->recordUnmatched($context, 'insufficient_recovery_evidence');
        }

        $candidates = $this->candidateQuery($event, $evidence)->get();

        if ($candidates->isEmpty()) {
            return $this->recordUnmatched($context, 'no_matching_case');
        }

        if ($candidates->count() > 1) {
            return $this->recordUnmatched($context, 'ambiguous_match');
        }

        return DB::transaction(function () use ($candidates, $context, $event, $evidence) {
            /** @var FailedPaymentCase $case */
            $case = FailedPaymentCase::query()->whereKey($candidates->first()->id)->lockForUpdate()->first();

            if (! $case) {
                return $this->recordUnmatched($context, 'case_disappeared');
            }

            if (! $this->eventMatchesCase($event, $case, $evidence)) {
                return $this->recordUnmatched($context, 'unsafe_match_rejected');
            }

            if (in_array($case->current_status, [
                FailedPaymentCaseStatus::RECOVERED,
                FailedPaymentCaseStatus::RESOLVED,
                FailedPaymentCaseStatus::CANCELLED,
            ], true)) {
                return $this->recordUnmatched($context, 'case_final_state');
            }

            $now = Carbon::now();
            $paymentHistoryUpdated = $this->markPaymentHistoryPaid($case, array_merge($context, [
                'anet_transaction_id' => Arr::get($context, 'anet_transaction_id') ?: Arr::get($evidence, 'anet_transaction_id'),
                'source' => Arr::get($context, 'source', 'verified_success'),
            ]), $now);
            $case->current_status = FailedPaymentCaseStatus::RECOVERED;
            $case->recovered_at = $case->recovered_at ?? $now;
            $case->resolved_at = $case->resolved_at ?? $now;
            $case->next_action_at = null;
            $case->metadata = array_replace_recursive((array) ($case->metadata ?? []), [
                'retry_state' => 'recovered',
                'recovery_source' => Arr::get($context, 'source', 'verified_success'),
                'recovery_event_id' => Arr::get($context, 'event_id'),
                'recovery_webhook_event_id' => $event->id,
                'last_successful_event_at' => $now->toIso8601String(),
                'last_successful_event_transaction_id' => Arr::get($context, 'anet_transaction_id'),
                'last_successful_event_subscription_id' => Arr::get($context, 'arb_subscription_id'),
            ]);
            $case->save();

            $this->cancelRemainingSchedules($case, $context, $now);
            $this->recordMatchedEvent($event, $case, $context, $now);

            FailedPaymentStatusHistory::create([
                'failed_payment_case_id' => $case->id,
                'previous_status' => FailedPaymentCaseStatus::OPEN,
                'new_status' => FailedPaymentCaseStatus::RECOVERED,
                'change_reason' => Arr::get($context, 'reason', 'Verified successful payment matched the failed-payment case.'),
                'changed_by_type' => 'system',
                'changed_by_user_id' => null,
                'metadata' => [
                    'recovery_event_id' => Arr::get($context, 'event_id'),
                    'recovery_source' => Arr::get($context, 'source', 'verified_success'),
                ],
                'created_at' => $now,
            ]);

            FailedPaymentAuditLog::create([
                'failed_payment_case_id' => $case->id,
                'previous_status' => FailedPaymentCaseStatus::OPEN,
                'new_status' => FailedPaymentCaseStatus::RECOVERED,
                'action' => 'recovery_recorded',
                'source' => 'recovery_service',
                'admin_id' => null,
                'ip_address' => null,
                'safe_request_metadata' => [
                    'event_id' => Arr::get($context, 'event_id'),
                    'event_type' => Arr::get($context, 'event_type'),
                    'anet_transaction_id' => Arr::get($context, 'anet_transaction_id'),
                    'arb_subscription_id' => Arr::get($context, 'arb_subscription_id'),
                ],
                'notes' => 'Recovered failed-payment case from verified success signal.',
                'metadata' => [
                    'recovery_source' => Arr::get($context, 'source', 'verified_success'),
                ],
            ]);

            return [
                'status' => 'recovered',
                'reason_code' => 'matched_and_recovered',
                'case_id' => $case->id,
                'case_key' => $case->case_key,
                'event_id' => Arr::get($context, 'event_id'),
                'payment_history_updated' => $paymentHistoryUpdated,
            ];
        });
    }

    public function hasNewerVerifiedSuccessForCase(FailedPaymentCase $case): bool
    {
        if (empty($case->first_failed_at)) {
            return false;
        }

        $candidates = FailedPaymentWebhookEvent::query()
            ->where('provider', 'authorize_net')
            ->whereIn('signature_validation_status', $this->verifiedSignatureStatuses())
            ->whereIn('processing_status', $this->processedStatuses())
            ->whereIn('event_type', $this->allowlistedSuccessEventTypes())
            ->where(function ($query) use ($case) {
                $query->where('processed_at', '>', $case->first_failed_at)
                    ->orWhere(function ($query) use ($case) {
                        $query->whereNull('processed_at')
                            ->where('received_at', '>', $case->first_failed_at);
                    });
            })
            ->latest('processed_at')
            ->get();

        foreach ($candidates as $event) {
            $evidence = $this->extractSuccessEvidence($event);

            if ($this->eventMatchesCase($event, $case, $evidence)) {
                return true;
            }
        }

        return false;
    }

    protected function candidateQuery(FailedPaymentWebhookEvent $event, array $evidence)
    {
        $query = FailedPaymentCase::query();

        if (empty($evidence['authorize_account_id']) || empty($evidence['arb_subscription_id']) || empty($evidence['payment_history_id'])) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('authorize_account_id', $evidence['authorize_account_id'])
                ->where('arb_subscription_id', $evidence['arb_subscription_id'])
                ->where('payment_history_id', $evidence['payment_history_id']);
        }

        return $query
            ->whereNotIn('current_status', [
                FailedPaymentCaseStatus::RECOVERED,
                FailedPaymentCaseStatus::RESOLVED,
                FailedPaymentCaseStatus::CANCELLED,
            ])
            ->where(function ($query) use ($event) {
                $timestamp = $event->processed_at ?? $event->received_at;

                if ($timestamp) {
                    $query->where('first_failed_at', '<', $timestamp);
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    protected function cancelRemainingSchedules(FailedPaymentCase $case, array $context, Carbon $now): void
    {
        FailedPaymentRetrySchedule::query()
            ->where('failed_payment_case_id', $case->id)
            ->whereIn('status', [
                FailedPaymentRetryScheduleStatus::PENDING,
                FailedPaymentRetryScheduleStatus::PROCESSING,
                FailedPaymentRetryScheduleStatus::SIMULATED,
            ])
            ->update([
                'status' => FailedPaymentRetryScheduleStatus::CANCELLED,
                'completed_at' => $now,
                'cancellation_reason' => Arr::get($context, 'reason', 'Recovered via verified successful payment.'),
                'updated_at' => $now,
            ]);
    }

    protected function recordMatchedEvent(FailedPaymentWebhookEvent $event, FailedPaymentCase $case, array $context, Carbon $now): void
    {
        $event->failed_payment_case_id = $case->id;
        $event->processed_at = $event->processed_at ?? $now;
        $event->processing_status = 'processed';
        $event->metadata = array_replace_recursive((array) ($event->metadata ?? []), [
            'recovery_case_id' => $case->id,
            'recovery_case_key' => $case->case_key,
            'recovery_source' => Arr::get($context, 'source', 'verified_success'),
        ]);
        $event->save();
    }

    protected function sanitizeContext(array $context): array
    {
        return array_filter([
            'provider' => strtolower(trim((string) Arr::get($context, 'provider', 'authorize_net'))),
            'event_id' => Arr::get($context, 'event_id'),
            'event_type' => Arr::get($context, 'event_type'),
            'failed_payment_webhook_event_id' => Arr::get($context, 'failed_payment_webhook_event_id'),
            'anet_transaction_id' => Arr::get($context, 'anet_transaction_id'),
            'arb_subscription_id' => Arr::get($context, 'arb_subscription_id'),
            'client_id' => Arr::get($context, 'client_id'),
            'payment_history_id' => Arr::get($context, 'payment_history_id'),
            'reason' => Arr::get($context, 'reason'),
            'source' => Arr::get($context, 'source', 'verified_success'),
        ], fn ($value) => ! is_null($value) && $value !== '');
    }

    protected function recordUnmatched(array $context, string $reasonCode): array
    {
        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => null,
            'previous_status' => null,
            'new_status' => null,
            'action' => 'recovery_unmatched',
            'source' => 'recovery_service',
            'admin_id' => null,
            'ip_address' => null,
            'safe_request_metadata' => [
                'reason_code' => $reasonCode,
                'event_id' => Arr::get($context, 'event_id'),
                'event_type' => Arr::get($context, 'event_type'),
                'anet_transaction_id' => Arr::get($context, 'anet_transaction_id'),
                'arb_subscription_id' => Arr::get($context, 'arb_subscription_id'),
            ],
            'notes' => 'Verified success signal could not be safely matched to a single failed-payment case.',
            'metadata' => [
                'reason_code' => $reasonCode,
                'source' => Arr::get($context, 'source', 'verified_success'),
            ],
        ]);

        return [
            'status' => 'unmatched',
            'reason_code' => $reasonCode,
            'case_id' => null,
            'case_key' => null,
            'event_id' => Arr::get($context, 'event_id'),
        ];
    }

    protected function resolveVerifiedEvent(array $context): ?FailedPaymentWebhookEvent
    {
        $eventId = Arr::get($context, 'failed_payment_webhook_event_id') ?? Arr::get($context, 'event_id');

        if (empty($eventId)) {
            return null;
        }

        return FailedPaymentWebhookEvent::query()
            ->where(function ($query) use ($eventId) {
                $query->whereKey($eventId)
                    ->orWhere('event_id', $eventId);
            })
            ->first();
    }

    protected function isVerifiedSuccessEvent(FailedPaymentWebhookEvent $event): bool
    {
        return $event->provider === 'authorize_net'
            && in_array(strtolower((string) $event->signature_validation_status), $this->verifiedSignatureStatuses(), true)
            && in_array(strtolower((string) $event->processing_status), $this->processedStatuses(), true)
            && in_array((string) $event->event_type, $this->allowlistedSuccessEventTypes(), true);
    }

    protected function extractSuccessEvidence(FailedPaymentWebhookEvent $event): array
    {
        $payload = (array) ($event->sanitized_payload ?? []);

        return array_filter([
            'authorize_account_id' => $this->extractRecursiveValue($payload, ['authorize_account_id', 'authorizeAccountId']),
            'arb_subscription_id' => $this->extractRecursiveValue($payload, ['arb_subscription_id', 'subscription_id', 'subscriptionId']),
            'payment_history_id' => $this->extractRecursiveValue($payload, ['payment_history_id', 'paymentHistoryId']),
        ], fn ($value) => ! is_null($value) && $value !== '');
    }

    protected function eventMatchesCase(FailedPaymentWebhookEvent $event, FailedPaymentCase $case, array $evidence): bool
    {
        $timestamp = $event->processed_at ?? $event->received_at;

        if (! $timestamp || ! $case->first_failed_at || $timestamp->lessThanOrEqualTo($case->first_failed_at)) {
            return false;
        }

        return (string) Arr::get($evidence, 'authorize_account_id', '') !== ''
            && (string) Arr::get($evidence, 'arb_subscription_id', '') !== ''
            && (string) Arr::get($evidence, 'payment_history_id', '') !== ''
            && (string) $case->authorize_account_id === (string) Arr::get($evidence, 'authorize_account_id')
            && (string) $case->arb_subscription_id === (string) Arr::get($evidence, 'arb_subscription_id')
            && (string) $case->payment_history_id === (string) Arr::get($evidence, 'payment_history_id');
    }

    protected function hasStrongRecoveryEvidence(array $evidence): bool
    {
        return ! empty($evidence['authorize_account_id'])
            && ! empty($evidence['arb_subscription_id'])
            && ! empty($evidence['payment_history_id']);
    }

    protected function verifiedSignatureStatuses(): array
    {
        return ['valid', 'verified'];
    }

    protected function processedStatuses(): array
    {
        return ['processed', 'completed', 'verified'];
    }

    protected function allowlistedSuccessEventTypes(): array
    {
        return [
            'transaction.settled',
            'net.authorize.payment.authcapture.created',
            'invoice.paid',
        ];
    }

    protected function markPaymentHistoryPaid(FailedPaymentCase $case, array $context, ?Carbon $now = null): bool
    {
        $paymentHistoryIds = array_values(array_unique(array_filter(array_map(
            static fn ($value) => (int) $value,
            (array) Arr::get($context, 'payment_history_ids', [])
        ), static fn (int $value) => $value > 0)));

        if ($paymentHistoryIds === []) {
            $paymentHistoryId = (int) ($case->payment_history_id ?? Arr::get($context, 'payment_history_id', 0));
            if ($paymentHistoryId > 0) {
                $paymentHistoryIds = [$paymentHistoryId];
            }
        }

        if ($paymentHistoryIds === []) {
            return false;
        }

        $now ??= Carbon::now();
        $transactionId = Arr::get($context, 'anet_transaction_id') ?: Arr::get($context, 'gateway_reference');

        $histories = PaymentHistory::query()
            ->whereKey($paymentHistoryIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($histories->isEmpty()) {
            return false;
        }

        $updated = false;

        foreach ($paymentHistoryIds as $paymentHistoryId) {
            $history = $histories->get($paymentHistoryId);

            if (! $history) {
                continue;
            }

            if ((int) ($history->payment_status ?? 0) === 1) {
                continue;
            }

            $history->payment_status = 1;

            if (property_exists($history, 'status') || array_key_exists('status', $history->getAttributes())) {
                $history->status = 1;
            }

            if ($transactionId !== null && $transactionId !== '') {
                $history->transaction_id = (string) $transactionId;
            }

            if (empty($history->payment_date)) {
                $history->payment_date = $now->toDateTimeString();
            }

            $history->save();
            $updated = true;
        }

        return $updated;
    }

    protected function recordStatusHistory(FailedPaymentCase $case, ?string $previousStatus, string $newStatus, array $context, Carbon $now): void
    {
        FailedPaymentStatusHistory::create([
            'failed_payment_case_id' => $case->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'change_reason' => Arr::get($context, 'reason', 'Successful live retry matched the failed-payment case.'),
            'changed_by_type' => 'system',
            'changed_by_user_id' => null,
            'metadata' => [
                'recovery_source' => Arr::get($context, 'source', 'live_retry'),
                'recovery_transaction_id' => Arr::get($context, 'anet_transaction_id') ?: Arr::get($context, 'gateway_reference'),
            ],
            'created_at' => $now,
        ]);
    }

    protected function recordAuditRecovered(FailedPaymentCase $case, ?string $previousStatus, array $context, Carbon $now, string $notes): void
    {
        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => $case->id,
            'previous_status' => $previousStatus,
            'new_status' => FailedPaymentCaseStatus::RECOVERED,
            'action' => 'recovery_recorded',
            'source' => 'recovery_service',
            'admin_id' => null,
            'ip_address' => null,
            'safe_request_metadata' => [
                'event_id' => Arr::get($context, 'event_id'),
                'event_type' => Arr::get($context, 'event_type'),
                'anet_transaction_id' => Arr::get($context, 'anet_transaction_id') ?: Arr::get($context, 'gateway_reference'),
                'arb_subscription_id' => Arr::get($context, 'arb_subscription_id'),
            ],
            'notes' => $notes,
            'metadata' => [
                'recovery_source' => Arr::get($context, 'source', 'live_retry'),
                'recovery_transaction_id' => Arr::get($context, 'anet_transaction_id') ?: Arr::get($context, 'gateway_reference'),
            ],
        ]);
    }

    protected function buildArbSubscriptionCreateContext(FailedPaymentCase $case, array $context): ?array
    {
        $clientId = (int) (Arr::get($context, 'client_id', $case->client_id ?? 0));
        $authorizeAccountId = (int) (Arr::get($context, 'authorize_account_id', $case->authorize_account_id ?? 0));
        $paymentHistoryId = (int) (Arr::get($context, 'payment_history_id', $case->payment_history_id ?? 0));

        if ($clientId <= 0 || $authorizeAccountId <= 0) {
            return null;
        }

        return [
            'client_id' => $clientId,
            'payment_history_id' => $paymentHistoryId > 0 ? $paymentHistoryId : null,
            'authorize_account_id' => $authorizeAccountId,
            'arb_subscription_id' => trim((string) Arr::get($context, 'arb_subscription_id', $case->arb_subscription_id ?? '')),
            'anet_transaction_id' => Arr::get($context, 'anet_transaction_id') ?: Arr::get($context, 'gateway_reference'),
        ];
    }

    protected function attemptArbSubscriptionCreate(?array $payload): ?array
    {
        if (! $payload) {
            return null;
        }

        $clientId = (int) Arr::get($payload, 'client_id', 0);
        $authorizeAccountId = (int) Arr::get($payload, 'authorize_account_id', 0);
        $paymentHistoryId = (int) Arr::get($payload, 'payment_history_id', 0);
        $oldSubscriptionId = trim((string) Arr::get($payload, 'arb_subscription_id', ''));

        if ($clientId <= 0 || $authorizeAccountId <= 0) {
            return [
                'status' => 'skipped',
                'reason_code' => 'missing_subscription_context',
                'subscription_id' => null,
            ];
        }

        $account = AuthorizeAccount::query()->whereKey($authorizeAccountId)->first();
        $client = Client::query()->whereKey($clientId)->first();
        $paymentHistory = $paymentHistoryId > 0 ? PaymentHistory::query()->whereKey($paymentHistoryId)->first() : null;

        if (! $account || ! $client) {
            return [
                'status' => 'skipped',
                'reason_code' => 'missing_subscription_models',
                'subscription_id' => null,
                'authorize_account_id' => $authorizeAccountId,
                'client_id' => $clientId,
            ];
        }

        try {
            $startDate = $this->recoverArbStartDate($paymentHistory);

            // Re-establish the ARB subscription on the same merchant account,
            // billing the client's CIM profile, from the recovered payment's
            // amount/frequency. Ported from AuthorizeNetArbService::
            // createRecoveredPaymentSubscription onto the Phase 6c ArbGateway.
            $cardProfile = CardProfile::query()
                ->where('client_id', $client->id)
                ->whereNotNull('customer_profile_id')
                ->whereNotNull('payment_profile_id')
                ->latest('id')
                ->first();

            if (! $cardProfile) {
                return [
                    'status' => 'skipped',
                    'reason_code' => 'missing_card_profile',
                    'subscription_id' => null,
                    'authorize_account_id' => $authorizeAccountId,
                    'client_id' => $clientId,
                    'original_subscription_id' => $oldSubscriptionId !== '' ? $oldSubscriptionId : null,
                ];
            }

            $amount = (float) ($paymentHistory->amount ?? 0);
            $isYearly = ((string) $client->payment_structure) === '2';

            $subscriptionId = app(ArbGateway::class)->createSubscription(
                $account,
                (string) $cardProfile->customer_profile_id,
                (string) $cardProfile->payment_profile_id,
                $startDate,
                $amount > 0 ? $amount : (float) ($paymentHistory->amount ?? 1),
                $isYearly ? 12 : 1,
                'months',
                trim(($client->first_name ?? '').' Recovered Subscription'),
                999,
            );

            return [
                'status' => 'created',
                'reason_code' => 'arb_subscription_created',
                'subscription_id' => $subscriptionId,
                'authorize_account_id' => $authorizeAccountId,
                'client_id' => $clientId,
                'payment_history_id' => $paymentHistoryId > 0 ? $paymentHistoryId : null,
                'original_subscription_id' => $oldSubscriptionId !== '' ? $oldSubscriptionId : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed-payment ARB subscription creation failed after successful retry.', [
                'original_subscription_id' => $oldSubscriptionId !== '' ? $oldSubscriptionId : null,
                'authorize_account_id' => $authorizeAccountId,
                'client_id' => $clientId,
                'payment_history_id' => $paymentHistoryId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'reason_code' => 'arb_subscription_create_failed',
                'subscription_id' => null,
                'authorize_account_id' => $authorizeAccountId,
                'client_id' => $clientId,
                'payment_history_id' => $paymentHistoryId > 0 ? $paymentHistoryId : null,
                'original_subscription_id' => $oldSubscriptionId !== '' ? $oldSubscriptionId : null,
                'message' => $this->safeText($e->getMessage()) ?: 'ARB subscription creation failed after the successful retry.',
            ];
        }
    }

    protected function syncLocalClientAfterArbSubscriptionCreate(int $caseId, array $arbResult): void
    {
        if ($caseId <= 0) {
            return;
        }

        $clientId = (int) Arr::get($arbResult, 'client_id', 0);
        if ($clientId <= 0) {
            return;
        }

        DB::transaction(function () use ($caseId, $clientId, $arbResult): void {
            $case = FailedPaymentCase::query()->whereKey($caseId)->lockForUpdate()->first();
            $client = Client::query()->whereKey($clientId)->lockForUpdate()->first();
            $paymentHistoryId = (int) Arr::get($arbResult, 'payment_history_id', 0);
            $paymentHistory = $paymentHistoryId > 0
                ? PaymentHistory::query()->whereKey($paymentHistoryId)->lockForUpdate()->first()
                : null;
            $subscriptionId = (string) Arr::get($arbResult, 'subscription_id', '');

            if ($case) {
                $case->metadata = array_replace_recursive((array) ($case->metadata ?? []), [
                    'arb_subscription_created_status' => Arr::get($arbResult, 'status'),
                    'arb_subscription_created_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                    'arb_subscription_created_updated_at' => now()->toIso8601String(),
                    'arb_subscription_created_original_subscription_id' => Arr::get($arbResult, 'original_subscription_id'),
                    'last_successful_event_subscription_id' => $subscriptionId !== '' ? $subscriptionId : Arr::get($case->metadata ?? [], 'last_successful_event_subscription_id'),
                ]);
                if ($subscriptionId !== '') {
                    $case->arb_subscription_id = $subscriptionId;
                }
                $case->authorize_account_id = (int) Arr::get($arbResult, 'authorize_account_id', $case->authorize_account_id ?? 0);
                $case->save();

                FailedPaymentAuditLog::create([
                    'failed_payment_case_id' => $case->id,
                    'previous_status' => $case->current_status,
                    'new_status' => $case->current_status,
                    'action' => 'arb_subscription_created',
                    'source' => 'recovery_service',
                    'admin_id' => null,
                    'ip_address' => null,
                    'safe_request_metadata' => [
                        'subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
                        'original_subscription_id' => Arr::get($arbResult, 'original_subscription_id'),
                        'payment_history_id' => Arr::get($arbResult, 'payment_history_id'),
                        'authorize_account_id' => Arr::get($arbResult, 'authorize_account_id'),
                    ],
                    'notes' => 'Fresh ARB subscription created after a successful retry and linked to local payment records.',
                    'metadata' => [
                        'arb_subscription_created_status' => Arr::get($arbResult, 'status'),
                    ],
                ]);
            }

            if ($subscriptionId !== '') {
                PaymentHistory::query()
                    ->where('client_id', $clientId)
                    ->update([
                        'subscription_id' => $subscriptionId,
                    ]);
            }

            if ($paymentHistory) {
                $paymentHistory->subscription_id = $subscriptionId !== '' ? $subscriptionId : $paymentHistory->subscription_id;
                $paymentHistory->authorize_account_id = (int) Arr::get($arbResult, 'authorize_account_id', $paymentHistory->authorize_account_id ?? 0);
                $paymentHistory->payment_status = 1;

                if (property_exists($paymentHistory, 'status') || array_key_exists('status', $paymentHistory->getAttributes())) {
                    $paymentHistory->status = 1;
                }

                $paymentHistory->save();
            }

            if ($client) {
                $client->status = 1;
                $client->authorize_status = 'active';
                $client->payment_faild_date = null;
                $client->save();
            }
        });
    }

    protected function recoverArbStartDate(?PaymentHistory $paymentHistory): \Illuminate\Support\Carbon
    {
        // ArbGateway::createSubscription type-hints Illuminate\Support\Carbon,
        // so build one here rather than the bare Carbon\Carbon this file
        // otherwise uses.
        $baseDate = $paymentHistory?->payment_date
            ? \Illuminate\Support\Carbon::parse($paymentHistory->payment_date)
            : \Illuminate\Support\Carbon::now();

        return $baseDate->copy()->addDays(30);
    }

    protected function safeText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function extractRecursiveValue(array $payload, array $keys): mixed
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), array_map('strtolower', $keys), true) && ! is_array($value) && ! is_object($value)) {
                return $value;
            }

            if (is_array($value)) {
                $nested = $this->extractRecursiveValue($value, $keys);
                if ($nested !== null && $nested !== '') {
                    return $nested;
                }
            }
        }

        return null;
    }
}
