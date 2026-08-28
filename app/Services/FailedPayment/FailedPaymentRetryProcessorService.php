<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentAttemptStatus;
use App\Enums\FailedPaymentAttemptType;
use App\Enums\FailedPaymentRetryAttemptResult;
use App\Enums\FailedPaymentRetryExecutionIntent;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Enums\FailedPaymentSource;
use App\Models\FailedPaymentAttempt;
use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentRetrySchedule;
use App\Services\FailedPayment\Contracts\FailedPaymentRetryGatewayInterface;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FailedPaymentRetryProcessorService
{
    public function __construct(
        protected FailedPaymentRetryGatewayInterface $gateway,
        protected FailedPaymentRetryEligibilityService $eligibility,
        protected FailedPaymentRecoveryService $recovery,
    ) {}

    public function processSchedule(FailedPaymentRetrySchedule $schedule, array $context = []): array
    {
        $intent = $this->normalizeExecutionIntent($context);

        if ($intent === null) {
            return $this->result('blocked', FailedPaymentRetryAttemptResult::BLOCKED, (string) $schedule->status, 'unknown_execution_intent', 'Execution intent is not recognized.');
        }

        return DB::transaction(function () use ($schedule, $context, $intent) {
            $lockedSchedule = FailedPaymentRetrySchedule::query()
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedSchedule) {
                return $this->result('missing_schedule', FailedPaymentRetryAttemptResult::BLOCKED, FailedPaymentRetryScheduleStatus::SKIPPED, null, 'Retry schedule no longer exists.');
            }

            $decision = $this->eligibility->evaluate($lockedSchedule, array_merge($context, ['execution_intent' => $intent]));
            if (! $decision->eligible) {
                $auditKey = $this->auditKey($lockedSchedule, 'retry_skipped', $decision->reasonCode, $decision->state);
                if ((string) Arr::get((array) ($lockedSchedule->metadata ?? []), 'last_audit_key', '') !== $auditKey) {
                    $this->recordAudit($lockedSchedule, 'retry_skipped', $decision->reasonCode, $decision->safeReason, $decision->state);
                    $lockedSchedule->metadata = array_replace_recursive((array) ($lockedSchedule->metadata ?? []), [
                        'last_audit_key' => $auditKey,
                        'last_reason_code' => $decision->reasonCode,
                        'last_reason' => $decision->safeReason,
                    ]);
                    $lockedSchedule->save();
                }

                return $this->result('skipped', FailedPaymentRetryAttemptResult::BLOCKED, (string) $lockedSchedule->status, $decision->reasonCode, $decision->safeReason);
            }

            $processingMode = strtolower((string) config('failed_payments.retry_processing_mode', 'dry_run'));
            $gatewayDriver = strtolower((string) config('failed_payments.retry_gateway_driver', 'dry_run'));
            if ($intent === FailedPaymentRetryExecutionIntent::SIMULATION && ! config('failed_payments.dry_run_mode')) {
                return $this->result('blocked', FailedPaymentRetryAttemptResult::BLOCKED, (string) $lockedSchedule->status, 'dry_run_disabled', 'Dry-run mode is disabled.');
            }

            if (
                $intent !== FailedPaymentRetryExecutionIntent::SIMULATION
                && ($processingMode !== 'live' || $gatewayDriver !== 'live_cim')
            ) {
                return $this->blockedIntentResult($lockedSchedule, $intent);
            }

            $simulationKey = $this->simulationKey($lockedSchedule, $intent);
            $existingSimulationKey = (string) Arr::get((array) ($lockedSchedule->metadata ?? []), 'last_simulation_key', '');
            $existingExecutionIntent = (string) Arr::get((array) ($lockedSchedule->metadata ?? []), 'last_execution_intent', '');

            if ($intent === FailedPaymentRetryExecutionIntent::SIMULATION) {
                if ($existingExecutionIntent === FailedPaymentRetryExecutionIntent::SIMULATION && $existingSimulationKey !== '' && $existingSimulationKey === $simulationKey) {
                    return $this->result('duplicate_simulation', FailedPaymentRetryAttemptResult::DRY_RUN, FailedPaymentRetryScheduleStatus::PENDING, 'duplicate_simulation', 'Retry schedule was already simulated with the same key.');
                }
            }

            $request = $this->gatewayRequest($lockedSchedule, $simulationKey, $intent, $context);
            $gateway = $intent === FailedPaymentRetryExecutionIntent::SIMULATION
                ? $this->gateway
                : app(LiveFailedPaymentRetryGateway::class);
            $gatewayResult = $gateway->retry($request);

            if ($intent === FailedPaymentRetryExecutionIntent::SIMULATION) {
                $lockedSchedule->status = FailedPaymentRetryScheduleStatus::PENDING;
                $lockedSchedule->metadata = array_replace_recursive((array) ($lockedSchedule->metadata ?? []), [
                    'last_simulation_key' => $simulationKey,
                    'last_execution_intent' => $intent,
                    'last_attempt_result' => $gatewayResult->attemptResult,
                    'gateway_reference' => $gatewayResult->gatewayReference,
                    'last_safe_message' => $gatewayResult->safeMessage,
                    'simulation_count' => (int) Arr::get((array) ($lockedSchedule->metadata ?? []), 'simulation_count', 0) + 1,
                ]);
                $lockedSchedule->save();

                $this->recordAudit($lockedSchedule, 'retry_simulated', $gatewayResult->attemptResult, $gatewayResult->safeMessage ?? 'Retry processed in dry-run mode.', $gatewayResult->metadata);

                return $this->result('simulated', $gatewayResult->attemptResult, FailedPaymentRetryScheduleStatus::PENDING, 'dry_run', $gatewayResult->safeMessage, $gatewayResult->gatewayReference, $gatewayResult->metadata);
            }

            return $this->applyLiveResult($lockedSchedule, $gatewayResult, $request, $intent);
        });
    }

    protected function applyLiveResult(FailedPaymentRetrySchedule $schedule, FailedPaymentRetryResult $gatewayResult, array $request, string $intent): array
    {
        $now = Carbon::now();
        $scheduleStatus = match ($gatewayResult->attemptResult) {
            FailedPaymentRetryAttemptResult::SUCCESS => FailedPaymentRetryScheduleStatus::COMPLETED,
            FailedPaymentRetryAttemptResult::TEMPORARY_FAILURE => FailedPaymentRetryScheduleStatus::PENDING,
            FailedPaymentRetryAttemptResult::BLOCKED => FailedPaymentRetryScheduleStatus::SKIPPED,
            default => FailedPaymentRetryScheduleStatus::FAILED,
        };

        $schedule->status = $scheduleStatus;
        $schedule->locked_at = null;
        $schedule->processing_started_at = $schedule->processing_started_at ?? $now;
        $schedule->completed_at = in_array($scheduleStatus, [
            FailedPaymentRetryScheduleStatus::COMPLETED,
            FailedPaymentRetryScheduleStatus::FAILED,
            FailedPaymentRetryScheduleStatus::SKIPPED,
        ], true) ? $now : $schedule->completed_at;
        $schedule->metadata = array_replace_recursive((array) ($schedule->metadata ?? []), [
            'last_execution_intent' => $intent,
            'last_attempt_result' => $gatewayResult->attemptResult,
            'gateway_reference' => $gatewayResult->gatewayReference,
            'last_safe_message' => $gatewayResult->safeMessage,
            'last_live_attempt_at' => $now->toIso8601String(),
        ]);
        $schedule->save();

        $case = $schedule->failedPaymentCase;
        if ($case) {
            $resolvedPaymentHistoryId = (int) data_get($gatewayResult->metadata, 'resolved_payment_history_id', $case->payment_history_id);
            $attemptStatus = $gatewayResult->attemptResult === FailedPaymentRetryAttemptResult::SUCCESS
                ? FailedPaymentAttemptStatus::RECORDED
                : FailedPaymentAttemptStatus::OBSERVED_FAILED;
            $idempotencyKey = $this->retryAttemptIdempotencyKey($schedule, $case, $intent);
            $attemptNumber = (int) $case->attempt_count + 1;
            $attemptAttributes = [
                'failed_payment_case_id' => $case->id,
                'payment_history_id' => $resolvedPaymentHistoryId > 0 ? $resolvedPaymentHistoryId : $case->payment_history_id,
                'attempt_number' => $attemptNumber,
                'attempt_type' => FailedPaymentAttemptType::RECONCILIATION_FAILURE,
                'attempt_source' => FailedPaymentSource::RECURRING_HEALTH,
                'scheduled_at' => $schedule->scheduled_at,
                'started_at' => $now,
                'completed_at' => $now,
                'amount' => (float) ($request['amount'] ?? $case->failed_amount ?? 0),
                'status' => $attemptStatus,
                'gateway_response_code' => $gatewayResult->attemptResult,
                'gateway_response_message' => $gatewayResult->safeMessage,
                'anet_transaction_id' => $gatewayResult->gatewayReference,
                'idempotency_key' => $idempotencyKey,
                'is_automatic' => $intent === FailedPaymentRetryExecutionIntent::AUTOMATIC,
                'metadata' => array_filter([
                    'retry_schedule_id' => $schedule->id,
                    'retry_attempt_result' => $gatewayResult->attemptResult,
                    'gateway_reference' => $gatewayResult->gatewayReference,
                ], fn ($value) => ! is_null($value) && $value !== ''),
            ];

            FailedPaymentAttempt::query()->updateOrCreate(
                [
                    'failed_payment_case_id' => $case->id,
                    'attempt_number' => $attemptNumber,
                ],
                $attemptAttributes
            );
        }

        if ($gatewayResult->attemptResult === FailedPaymentRetryAttemptResult::SUCCESS && $case) {
            $recovery = $this->recovery->recoverFromSuccessfulRetry($case, array_merge($request, (array) $gatewayResult->metadata, [
                'source' => $intent,
                'gateway_reference' => $gatewayResult->gatewayReference,
                'anet_transaction_id' => $gatewayResult->gatewayReference,
            ]));

            $this->recordAudit($schedule, 'retry_live_success', $gatewayResult->attemptResult, $gatewayResult->safeMessage ?? 'Live retry completed successfully.', array_merge($gatewayResult->metadata, [
                'recovery_result' => $recovery['status'] ?? null,
            ]));

            return $this->result('completed', $gatewayResult->attemptResult, $scheduleStatus, 'live_retry_success', $gatewayResult->safeMessage, $gatewayResult->gatewayReference, array_merge($gatewayResult->metadata, [
                'recovery_result' => $recovery['status'] ?? null,
            ]));
        }

        $action = $gatewayResult->attemptResult === FailedPaymentRetryAttemptResult::TEMPORARY_FAILURE
            ? 'retry_live_temporary_failure'
            : 'retry_live_failed';

        $this->recordAudit($schedule, $action, $gatewayResult->attemptResult, $gatewayResult->safeMessage ?? 'Live retry processed.', $gatewayResult->metadata);

        return $this->result(
            $gatewayResult->attemptResult === FailedPaymentRetryAttemptResult::TEMPORARY_FAILURE ? 'deferred' : 'failed',
            $gatewayResult->attemptResult,
            $scheduleStatus,
            $gatewayResult->attemptResult === FailedPaymentRetryAttemptResult::TEMPORARY_FAILURE ? 'live_retry_temporary_failure' : 'live_retry_failed',
            $gatewayResult->safeMessage,
            $gatewayResult->gatewayReference,
            $gatewayResult->metadata
        );
    }

    protected function gatewayRequest(FailedPaymentRetrySchedule $schedule, string $simulationKey, string $intent, array $context): array
    {
        $case = $schedule->failedPaymentCase;

        return array_merge([
            'simulation_key' => $simulationKey,
            'execution_intent' => $intent,
            'retry_schedule_id' => $schedule->id,
            'case_id' => $case?->id,
            'case_key' => $case?->case_key,
            'sequence_number' => $schedule->sequence_number,
            'scheduled_at' => optional($schedule->scheduled_at)->toIso8601String(),
            'amount' => (float) ($case?->failed_amount ?? 0),
            'currency' => $case?->currency,
            'client_id' => $case?->client_id,
            'payment_history_id' => $case?->payment_history_id,
            'authorize_account_id' => $case?->authorize_account_id,
            'arb_subscription_id' => $case?->arb_subscription_id,
            'anet_transaction_id' => $case?->anet_transaction_id,
            'failure_category' => $case?->failure_category,
            'failure_code' => $case?->failure_code,
            'failure_reason' => $case?->failure_reason,
            'dry_run_mode' => (bool) config('failed_payments.dry_run_mode', true),
            'retry_timezone' => config('failed_payments.retry_timezone', config('app.timezone', 'UTC')),
        ], $context);
    }

    protected function simulationKey(FailedPaymentRetrySchedule $schedule, string $intent): string
    {
        return hash('sha256', implode('|', [
            'failed_payment_retry_simulation',
            $schedule->failed_payment_case_id,
            $schedule->sequence_number,
            optional($schedule->scheduled_at)->toIso8601String(),
            optional($schedule->failedPaymentCase?->first_failed_at)->toIso8601String(),
            config('failed_payments.retry_timezone', config('app.timezone', 'UTC')),
            $intent,
        ]));
    }

    protected function retryAttemptIdempotencyKey(FailedPaymentRetrySchedule $schedule, $case, string $intent): string
    {
        return hash('sha256', implode('|', [
            'failed_payment_retry_attempt',
            'schedule:'.$schedule->id,
            'case:'.$schedule->failed_payment_case_id,
            'case_key:'.(string) ($case->case_key ?? ''),
            'sequence:'.(string) $schedule->sequence_number,
            'scheduled_at:'.optional($schedule->scheduled_at)->toIso8601String(),
            'intent:'.$intent,
            'attempt_type:'.FailedPaymentAttemptType::RECONCILIATION_FAILURE,
            'attempt_source:'.FailedPaymentSource::RECURRING_HEALTH,
        ]));
    }

    protected function normalizeExecutionIntent(array $context): ?string
    {
        return FailedPaymentRetryExecutionIntent::normalize(Arr::get($context, 'execution_intent', FailedPaymentRetryExecutionIntent::SIMULATION));
    }

    protected function blockedIntentResult(FailedPaymentRetrySchedule $schedule, string $intent): array
    {
        $reasonCode = $intent === FailedPaymentRetryExecutionIntent::AUTOMATIC
            ? 'gateway_unavailable'
            : 'gateway_unavailable';

        $safeReason = $intent === FailedPaymentRetryExecutionIntent::AUTOMATIC
            ? 'Automatic retry is enabled, but no live retry gateway is configured.'
            : 'Manual retry is enabled, but no live retry gateway is configured.';

        return $this->result('blocked', FailedPaymentRetryAttemptResult::BLOCKED, (string) $schedule->status, $reasonCode, $safeReason);
    }

    protected function recordAudit(FailedPaymentRetrySchedule $schedule, string $action, string $reasonCode, string $safeReason, array $metadata = []): void
    {
        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => $schedule->failed_payment_case_id,
            'previous_status' => null,
            'new_status' => $schedule->status,
            'action' => $action,
            'source' => 'retry_processor_service',
            'admin_id' => null,
            'ip_address' => null,
            'safe_request_metadata' => [
                'retry_schedule_id' => $schedule->id,
                'sequence_number' => $schedule->sequence_number,
                'reason_code' => $reasonCode,
                'safe_reason' => $safeReason,
            ],
            'notes' => $safeReason,
            'metadata' => array_filter($metadata, fn ($value) => ! is_null($value) && $value !== ''),
        ]);
    }

    protected function auditKey(FailedPaymentRetrySchedule $schedule, string $action, string $reasonCode, array $state = []): string
    {
        return hash('sha256', implode('|', [
            $schedule->id,
            $action,
            $reasonCode,
            $schedule->status,
            Arr::get($state, 'evaluated_at', ''),
            Arr::get($state, 'scheduled_at', ''),
        ]));
    }

    protected function result(string $status, string $attemptResult, string $scheduleStatus, string $reasonCode, ?string $safeReason = null, ?string $gatewayReference = null, array $metadata = []): array
    {
        return [
            'status' => $status,
            'attempt_result' => $attemptResult,
            'schedule_status' => $scheduleStatus,
            'reason_code' => $reasonCode,
            'safe_reason' => $safeReason,
            'gateway_reference' => $gatewayReference,
            'metadata' => $metadata,
        ];
    }
}
