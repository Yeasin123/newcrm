<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentCaseStatus;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentRetrySchedule;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class FailedPaymentRetryScheduleService
{
    public function createForCase(FailedPaymentCase $case, array $context = []): array
    {
        return $this->createForCaseResult($case, $context)['created_schedules'];
    }

    public function createForCaseResult(FailedPaymentCase $case, array $context = []): array
    {
        if (! $this->hasOriginalFailureTimestamp($case)) {
            $this->recordSeedFailure($case, $context, 'missing_first_failed_at', 'Original failure timestamp is missing or invalid.');

            return [
                'status' => 'failed',
                'reason_code' => 'missing_first_failed_at',
                'safe_reason' => 'Original failure timestamp is missing or invalid.',
                'created_schedules' => [],
            ];
        }

        if (! $this->shouldSchedule($case)) {
            return [
                'status' => 'skipped',
                'reason_code' => 'not_eligible',
                'safe_reason' => 'Failed-payment case is not eligible for retry schedule creation.',
                'created_schedules' => [],
            ];
        }

        $days = $this->retryDays();
        if ($days === []) {
            return [
                'status' => 'skipped',
                'reason_code' => 'no_retry_days',
                'safe_reason' => 'No retry days are configured.',
                'created_schedules' => [],
            ];
        }

        $failedAt = Carbon::parse($case->first_failed_at, $this->timezone());
        $created = [];

        foreach ($days as $index => $day) {
            $sequence = $index + 1;
            $scheduledAt = $this->scheduledAtForDay($failedAt, $day);
            $schedule = FailedPaymentRetrySchedule::firstOrNew([
                'failed_payment_case_id' => $case->id,
                'sequence_number' => $sequence,
            ]);

            $isNew = ! $schedule->exists;
            $needsTimeSync = ! $isNew
                && $schedule->status === FailedPaymentRetryScheduleStatus::PENDING
                && (
                    (int) ($schedule->retry_delay_days ?? 0) !== $day
                    || ! $schedule->scheduled_at
                    || ! $schedule->scheduled_at->equalTo($scheduledAt)
                );

            if ($isNew || $needsTimeSync) {
                $schedule->retry_delay_days = $day;
                $schedule->scheduled_at = $scheduledAt;
                $schedule->status = $schedule->status ?: FailedPaymentRetryScheduleStatus::PENDING;
                $schedule->metadata = array_replace_recursive((array) ($schedule->metadata ?? []), [
                    'original_failed_at' => $failedAt->toIso8601String(),
                    'retry_timezone' => $this->timezone(),
                    'retry_time' => $this->retryTime(),
                    'retry_policy' => 'original_failed_at_plus_days_and_time',
                    'scheduled_by' => 'failed_payment_capture_service',
                ]);
                $schedule->save();
            }

            if ($isNew) {
                $created[] = $schedule;

                FailedPaymentAuditLog::create([
                    'failed_payment_case_id' => $case->id,
                    'previous_status' => null,
                    'new_status' => null,
                    'action' => 'retry_schedule_created',
                    'source' => 'retry_schedule_service',
                    'admin_id' => null,
                    'ip_address' => null,
                    'safe_request_metadata' => [
                        'case_key' => $case->case_key,
                        'sequence_number' => $sequence,
                        'retry_delay_days' => $day,
                        'scheduled_at' => $scheduledAt->toIso8601String(),
                        'retry_time' => $this->retryTime(),
                    ],
                    'notes' => 'Retry schedule created for failed-payment processing.',
                    'metadata' => array_filter([
                        'simulation_key' => $this->scheduleSimulationKey($case, $sequence, $day),
                        'source' => 'phase2b',
                        'context' => $context,
                    ], fn ($value) => ! is_null($value) && $value !== ''),
                ]);
            }
        }

        return [
            'status' => 'created',
            'reason_code' => 'created',
            'safe_reason' => null,
            'created_schedules' => $created,
        ];
    }

    public function shouldSchedule(FailedPaymentCase $case): bool
    {
        if (! $this->hasOriginalFailureTimestamp($case)) {
            return false;
        }

        if (! $case->failed_amount || (float) $case->failed_amount <= 0) {
            return false;
        }

        if (! $this->isRetryableCategory((string) $case->failure_category)) {
            return false;
        }

        if (in_array($case->current_status, [
            FailedPaymentCaseStatus::RECOVERED,
            FailedPaymentCaseStatus::RESOLVED,
            FailedPaymentCaseStatus::CANCELLED,
        ], true)) {
            return false;
        }

        $retryState = (string) Arr::get((array) ($case->metadata ?? []), 'retry_state', '');
        if (in_array($retryState, ['ignored', 'duplicate', 'exhausted'], true)) {
            return false;
        }

        return ! empty($case->client_id)
            && ! empty($case->authorize_account_id)
            && ! empty($case->arb_subscription_id);
    }

    protected function hasOriginalFailureTimestamp(FailedPaymentCase $case): bool
    {
        if (empty($case->first_failed_at)) {
            return false;
        }

        try {
            Carbon::parse($case->first_failed_at, $this->timezone());

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function timezone(): string
    {
        return (string) config('failed_payments.retry_timezone', config('app.timezone', 'UTC'));
    }

    protected function retryDays(): array
    {
        $days = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) config('failed_payments.retry_days', [1, 3, 7, 14, 30])
        ), fn ($day) => $day > 0)));

        sort($days);

        return $days;
    }

    protected function retryTime(): string
    {
        $value = trim((string) config('failed_payments.retry_time', '12:00'));

        return preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $value)
            ? str_pad(explode(':', $value)[0], 2, '0', STR_PAD_LEFT).':'.explode(':', $value)[1]
            : '12:00';
    }

    protected function scheduledAtForDay(Carbon $failedAt, int $day): Carbon
    {
        $scheduledAt = $failedAt->copy()->addDays($day);
        [$hour, $minute] = array_map('intval', explode(':', $this->retryTime()));

        return $scheduledAt->setTime($hour, $minute, 0);
    }

    protected function isRetryableCategory(string $category): bool
    {
        return app(FailedPaymentFailureCategoryMapper::class)->isRetryable(
            app(FailedPaymentFailureCategoryMapper::class)->normalize($category)
        );
    }

    protected function scheduleSimulationKey(FailedPaymentCase $case, int $sequenceNumber, int $delayDays): string
    {
        return hash('sha256', implode('|', [
            'failed_payment_retry_schedule',
            $case->case_key,
            $sequenceNumber,
            $delayDays,
            $this->retryTime(),
            optional($case->first_failed_at ?? $case->latest_failed_at)->toIso8601String(),
        ]));
    }

    protected function recordSeedFailure(FailedPaymentCase $case, array $context, string $reasonCode, string $safeReason): void
    {
        if (empty($case->id)) {
            return;
        }

        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => $case->id,
            'previous_status' => $case->current_status ?? null,
            'new_status' => $case->current_status ?? null,
            'action' => 'retry_schedule_seed_failed',
            'source' => 'retry_schedule_service',
            'admin_id' => null,
            'ip_address' => null,
            'safe_request_metadata' => [
                'case_key' => $case->case_key,
                'reason_code' => $reasonCode,
            ],
            'notes' => $safeReason,
            'metadata' => array_filter([
                'context' => $context,
                'reason_code' => $reasonCode,
            ], fn ($value) => ! is_null($value) && $value !== ''),
        ]);
    }
}
