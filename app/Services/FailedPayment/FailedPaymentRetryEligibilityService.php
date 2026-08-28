<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentCaseStatus;
use App\Enums\FailedPaymentRetryExecutionIntent;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\CardProfile;
use App\Models\FailedPaymentRetrySchedule;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class FailedPaymentRetryEligibilityService
{
    public function evaluate(FailedPaymentRetrySchedule $schedule, array $context = []): FailedPaymentRetryDecision
    {
        $case = $schedule->failedPaymentCase;
        $timezone = (string) config('failed_payments.retry_timezone', config('app.timezone', 'UTC'));
        $evaluatedAt = Carbon::now($timezone);
        $intent = $this->normalizeExecutionIntent($context);
        $state = $this->stateSnapshot($schedule, $case, $evaluatedAt);
        $state['execution_intent'] = $intent ?? Arr::get($context, 'execution_intent');

        if (! config('failed_payments.module_enabled')) {
            return $this->decision(false, 'module_disabled', 'Failed-payment module is disabled.', $evaluatedAt, $state);
        }

        if ($intent === null) {
            return $this->decision(false, 'unknown_execution_intent', 'Execution intent is not recognized.', $evaluatedAt, $state);
        }

        if ($intent === FailedPaymentRetryExecutionIntent::SIMULATION && ! config('failed_payments.dry_run_mode')) {
            return $this->decision(false, 'dry_run_disabled', 'Dry-run mode is disabled.', $evaluatedAt, $state);
        }

        if ($intent === FailedPaymentRetryExecutionIntent::AUTOMATIC && ! config('failed_payments.automatic_retry_enabled')) {
            return $this->decision(false, 'automatic_retry_disabled', 'Automatic retry is disabled.', $evaluatedAt, $state);
        }

        if ($intent === FailedPaymentRetryExecutionIntent::MANUAL && ! config('failed_payments.manual_retry_enabled')) {
            return $this->decision(false, 'manual_retry_disabled', 'Manual retry is disabled.', $evaluatedAt, $state);
        }

        if (! $case) {
            return $this->decision(false, 'missing_case', 'Retry schedule has no case.', $evaluatedAt, $state);
        }

        if (! $this->hasUnpaidPaymentHistory($case)) {
            return $this->decision(false, 'payment_already_successful', 'Payment history is already marked as paid.', $evaluatedAt, $state);
        }

        if ($this->isFinalCaseState($case->current_status, $case->metadata ?? [])) {
            return $this->decision(false, 'case_final_state', 'Case is already in a final state.', $evaluatedAt, $state);
        }

        if ($schedule->status === FailedPaymentRetryScheduleStatus::PAUSED) {
            return $this->decision(false, 'schedule_paused', 'Retry schedule is paused.', $evaluatedAt, $state);
        }

        $category = app(FailedPaymentFailureCategoryMapper::class)->normalize((string) ($case->failure_category ?? ''));
        $state['normalized_failure_category'] = $category;

        if (! app(FailedPaymentFailureCategoryMapper::class)->isRetryable($category)) {
            return $this->decision(false, 'non_retryable_category', 'Failure category is not retryable.', $evaluatedAt, $state);
        }

        $amount = (float) ($case->failed_amount ?? 0);
        if ($amount <= 0) {
            return $this->decision(false, 'invalid_amount', 'Retry amount is missing or invalid.', $evaluatedAt, $state);
        }

        if (! $this->hasRequiredReferences($case)) {
            return $this->decision(false, 'missing_reference', 'Required retry references are missing.', $evaluatedAt, $state);
        }

        if (! $this->hasPaymentProfileReference($case)) {
            return $this->decision(false, 'missing_payment_profile_reference', 'Payment profile reference is missing.', $evaluatedAt, $state);
        }

        if ($this->hasNewerSuccessfulModuleEvent($case)) {
            return $this->decision(false, 'newer_successful_event_exists', 'A newer successful module event already exists.', $evaluatedAt, $state);
        }

        if ($this->isScheduleCompleted($schedule)) {
            return $this->decision(false, 'schedule_completed', 'Retry schedule is already completed.', $evaluatedAt, $state);
        }

        if ($this->isScheduleTerminal($schedule->status)) {
            return $this->decision(false, 'schedule_terminal', 'Retry schedule is in a terminal state.', $evaluatedAt, $state);
        }

        if ($this->isScheduleProcessing($schedule) && ! $this->hasStaleLock($schedule, $evaluatedAt)) {
            return $this->decision(false, 'schedule_processing', 'Another worker is currently processing the retry schedule.', $evaluatedAt, $state);
        }

        if (! $this->isDue($schedule, $evaluatedAt)) {
            return $this->decision(false, 'not_yet_due', 'Retry schedule is not due yet.', $evaluatedAt, $state);
        }

        $maximumAttempts = (int) config('failed_payments.maximum_retry_attempts', 5);
        if ((int) $schedule->sequence_number > $maximumAttempts) {
            return $this->decision(false, 'maximum_attempts_reached', 'Maximum retry attempts have been reached.', $evaluatedAt, $state);
        }

        if ($this->isFinalCaseState($case->current_status, $case->metadata ?? [])) {
            return $this->decision(false, 'case_final_state', 'Case is already in a final state.', $evaluatedAt, $state);
        }

        if (data_get($case->metadata, 'retry_state') === 'exhausted') {
            return $this->decision(false, 'case_exhausted', 'Case retry opportunities are exhausted.', $evaluatedAt, $state);
        }

        return $this->decision(true, 'eligible', 'Retry schedule is eligible for dry-run processing.', $evaluatedAt, $state);
    }

    protected function stateSnapshot(FailedPaymentRetrySchedule $schedule, $case, Carbon $evaluatedAt): array
    {
        return [
            'evaluated_at' => $evaluatedAt->toIso8601String(),
            'module_enabled' => (bool) config('failed_payments.module_enabled'),
            'automatic_retry_enabled' => (bool) config('failed_payments.automatic_retry_enabled'),
            'manual_retry_enabled' => (bool) config('failed_payments.manual_retry_enabled'),
            'dry_run_mode' => (bool) config('failed_payments.dry_run_mode'),
            'schedule_status' => $schedule->status,
            'schedule_sequence_number' => $schedule->sequence_number,
            'scheduled_at' => optional($schedule->scheduled_at)->toIso8601String(),
            'locked_at' => optional($schedule->locked_at)->toIso8601String(),
            'processing_started_at' => optional($schedule->processing_started_at)->toIso8601String(),
            'case_status' => $case?->current_status,
            'case_key' => $case?->case_key,
            'client_id' => $case?->client_id,
            'case_failed_amount' => $case?->failed_amount,
            'failure_category' => $case?->failure_category,
            'payment_history_status' => $case?->paymentHistory?->payment_status,
            'retry_timezone' => config('failed_payments.retry_timezone', config('app.timezone', 'UTC')),
        ];
    }

    protected function hasRequiredReferences($case): bool
    {
        return ! empty($case->client_id)
            && ! empty($case->authorize_account_id)
            && ! empty($case->arb_subscription_id);
    }

    protected function hasPaymentProfileReference($case): bool
    {
        $metadata = (array) ($case->metadata ?? []);

        return ! empty($metadata['payment_profile_id'] ?? null)
            || ! empty($metadata['customer_profile_id'] ?? null)
            || ! empty($metadata['payment_profile_reference'] ?? null)
            || $this->hasSavedCardProfile((int) ($case->client_id ?? 0));
    }

    protected function hasUnpaidPaymentHistory($case): bool
    {
        if (! $case) {
            return false;
        }

        $case->loadMissing(['client.paymentHistories']);

        return $case->client?->paymentHistories?->contains(function ($paymentHistory): bool {
            return (int) ($paymentHistory->payment_status ?? 0) === 0;
        }) ?? false;
    }

    protected function hasSavedCardProfile(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        return CardProfile::query()
            ->where('client_id', $clientId)
            ->whereNotNull('customer_profile_id')
            ->whereNotNull('payment_profile_id')
            ->exists();
    }

    protected function hasNewerSuccessfulModuleEvent($case): bool
    {
        return app(FailedPaymentRecoveryService::class)->hasNewerVerifiedSuccessForCase($case);
    }

    protected function normalizeExecutionIntent(array $context): ?string
    {
        return FailedPaymentRetryExecutionIntent::normalize(Arr::get($context, 'execution_intent', FailedPaymentRetryExecutionIntent::SIMULATION));
    }

    protected function isDue(FailedPaymentRetrySchedule $schedule, Carbon $evaluatedAt): bool
    {
        return ! $schedule->scheduled_at || $schedule->scheduled_at->lessThanOrEqualTo($evaluatedAt);
    }

    protected function isScheduleTerminal(?string $status): bool
    {
        return in_array($status, [
            FailedPaymentRetryScheduleStatus::COMPLETED,
            FailedPaymentRetryScheduleStatus::PAUSED,
            FailedPaymentRetryScheduleStatus::CANCELLED,
            FailedPaymentRetryScheduleStatus::SKIPPED,
            FailedPaymentRetryScheduleStatus::FAILED,
            FailedPaymentRetryScheduleStatus::EXHAUSTED,
        ], true);
    }

    protected function isScheduleProcessing(FailedPaymentRetrySchedule $schedule): bool
    {
        return $schedule->status === FailedPaymentRetryScheduleStatus::PROCESSING;
    }

    protected function hasStaleLock(FailedPaymentRetrySchedule $schedule, Carbon $evaluatedAt): bool
    {
        if (! $schedule->locked_at) {
            return true;
        }

        $timeout = (int) config('failed_payments.retry_lock_timeout_minutes', 15);

        return $schedule->locked_at->copy()->addMinutes($timeout)->lessThanOrEqualTo($evaluatedAt);
    }

    protected function isScheduleCompleted(FailedPaymentRetrySchedule $schedule): bool
    {
        return $schedule->status === FailedPaymentRetryScheduleStatus::COMPLETED;
    }

    protected function isFinalCaseState(?string $status, array $metadata): bool
    {
        $retryState = (string) Arr::get($metadata, 'retry_state', '');

        return in_array($status, [
            FailedPaymentCaseStatus::RECOVERED,
            FailedPaymentCaseStatus::RESOLVED,
            FailedPaymentCaseStatus::CANCELLED,
        ], true) || in_array($retryState, [
            'ignored',
            'duplicate',
            'exhausted',
        ], true);
    }

    protected function decision(bool $eligible, string $reasonCode, string $safeReason, Carbon $evaluatedAt, array $state = []): FailedPaymentRetryDecision
    {
        return new FailedPaymentRetryDecision($eligible, $reasonCode, $safeReason, $evaluatedAt, $state);
    }
}
