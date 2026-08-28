<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentActionStatus;
use App\Enums\FailedPaymentActionType;
use App\Enums\FailedPaymentAttemptStatus;
use App\Enums\FailedPaymentAttemptType;
use App\Enums\FailedPaymentCaseStatus;
use App\Enums\FailedPaymentSource;
use App\Models\AnetWebhookEvent;
use App\Models\FailedPaymentAction;
use App\Models\FailedPaymentAttempt;
use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FailedPaymentCaptureService
{
    public function capture(array $context): ?FailedPaymentCase
    {
        if (! config('failed_payments.module_enabled') || ! config('failed_payments.capture_enabled')) {
            return null;
        }

        $case = null;

        try {
            $case = DB::transaction(function () use ($context) {
                $context = $this->sanitizeContext($context);
                $case = $this->resolveCase($context);

                $created = false;
                if (! $case) {
                    $case = $this->createCase($context);
                    $created = true;
                } else {
                    $this->mergeCaseSnapshot($case, $context);
                }

                $attemptCreated = $this->recordAttempt($case, $context, $created);

                if ($created) {
                    $this->recordStatusHistory($case, null, $case->current_status, $context);
                }

                if ($created) {
                    $this->recordSystemAction($case, $context);
                }

                return $case->refresh();
            });
        } catch (\Throwable $e) {
            Log::error('Failed payment passive capture failed', [
                'error' => $e->getMessage(),
                'authorize_account_id' => $context['authorize_account_id'] ?? null,
                'client_id' => $context['client_id'] ?? null,
                'payment_history_id' => $context['payment_history_id'] ?? null,
                'anet_webhook_event_id' => $context['anet_webhook_event_id'] ?? null,
            ]);

            return null;
        }

        try {
            app(FailedPaymentRetryScheduleService::class)->createForCaseResult($case, $context);
        } catch (\Throwable $e) {
            $this->recordRetryScheduleSeedFailure($case, $context, 'retry_schedule_seed_failed', 'Retry schedule seeding failed after the case was captured.');

            Log::warning('Failed payment retry schedule seeding failed after capture', [
                'error' => $e->getMessage(),
                'failed_payment_case_id' => $case->id,
                'case_key' => $case->case_key,
            ]);
        }

        return $case;
    }

    protected function recordRetryScheduleSeedFailure(FailedPaymentCase $case, array $context, string $reasonCode, string $safeReason): void
    {
        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => $case->id,
            'previous_status' => $case->current_status,
            'new_status' => $case->current_status,
            'action' => 'retry_schedule_seed_failed',
            'source' => 'capture_service',
            'admin_id' => null,
            'ip_address' => null,
            'safe_request_metadata' => [
                'case_key' => $case->case_key,
                'reason_code' => $reasonCode,
            ],
            'notes' => $safeReason,
            'metadata' => [
                'reason_code' => $reasonCode,
                'context' => $context,
            ],
        ]);
    }

    protected function resolveCase(array $context): ?FailedPaymentCase
    {
        $query = FailedPaymentCase::query();

        $anetWebhookEventId = Arr::get($context, 'anet_webhook_event_id');
        if (! empty($anetWebhookEventId)) {
            $case = (clone $query)->where('anet_webhook_event_id', $anetWebhookEventId)->lockForUpdate()->first();
            if ($case) {
                return $case;
            }
        }

        $caseKey = $this->caseKey($context);
        if ($caseKey !== null) {
            $case = (clone $query)->where('case_key', $caseKey)->lockForUpdate()->first();
            if ($case) {
                return $case;
            }
        }

        return null;
    }

    protected function createCase(array $context): FailedPaymentCase
    {
        $status = ! empty($context['client_id']) || ! empty($context['payment_history_id'])
            ? FailedPaymentCaseStatus::NEW
            : FailedPaymentCaseStatus::UNMATCHED;

        $case = new FailedPaymentCase;
        $case->case_key = $this->caseKey($context) ?? (string) Str::uuid();
        $case->client_id = $context['client_id'] ?? null;
        $case->payment_history_id = $context['payment_history_id'] ?? null;
        $case->authorize_account_id = $context['authorize_account_id'] ?? null;
        $case->anet_webhook_event_id = $context['anet_webhook_event_id'] ?? null;
        $case->claim_id = $context['claim_id'] ?? null;
        $case->arb_subscription_id = $context['arb_subscription_id'] ?? null;
        $case->anet_transaction_id = $context['anet_transaction_id'] ?? null;
        $case->scheduled_payment_date = $this->dateOnly($context['scheduled_payment_date'] ?? null);
        $case->failed_amount = $this->decimalOrNull($context['failed_amount'] ?? null);
        $case->currency = $context['currency'] ?? null;
        $case->failure_code = $context['failure_code'] ?? null;
        $case->failure_reason = $context['failure_reason'] ?? null;
        $case->failure_category = $this->normalizeFailureCategory($context);
        $case->failure_source = $context['failure_source'] ?? FailedPaymentSource::AUTHORIZE_NET_WEBHOOK;
        $case->payment_frequency = $context['payment_frequency'] ?? null;
        $case->first_failed_at = $this->datetimeOrNull($context['first_failed_at'] ?? null) ?? now();
        $case->latest_failed_at = $this->datetimeOrNull($context['latest_failed_at'] ?? null) ?? now();
        $case->current_status = $status;
        $case->attempt_count = 1;
        $case->next_action_at = $this->datetimeOrNull($context['next_action_at'] ?? null);
        $case->recovered_at = $this->datetimeOrNull($context['recovered_at'] ?? null);
        $case->resolved_at = $this->datetimeOrNull($context['resolved_at'] ?? null);
        $case->assigned_user_id = $context['assigned_user_id'] ?? null;
        $case->priority = $context['priority'] ?? 'normal';
        $case->metadata = $context['metadata'] ?? [];
        $case->save();

        return $case;
    }

    protected function mergeCaseSnapshot(FailedPaymentCase $case, array $context): void
    {
        $changed = false;
        $previousStatus = $case->current_status;
        $statusChanged = false;

        foreach ([
            'client_id',
            'payment_history_id',
            'authorize_account_id',
            'anet_webhook_event_id',
            'claim_id',
            'arb_subscription_id',
            'anet_transaction_id',
            'currency',
            'failure_code',
            'failure_reason',
            'failure_category',
            'failure_source',
            'payment_frequency',
            'assigned_user_id',
            'priority',
        ] as $field) {
            if (empty($case->{$field}) && array_key_exists($field, $context) && ! is_null($context[$field])) {
                $case->{$field} = $context[$field];
                $changed = true;
            }
        }

        if (! $case->scheduled_payment_date && ! empty($context['scheduled_payment_date'])) {
            $case->scheduled_payment_date = $this->dateOnly($context['scheduled_payment_date']);
            $changed = true;
        }

        if (! $case->failed_amount && array_key_exists('failed_amount', $context) && ! is_null($context['failed_amount'])) {
            $case->failed_amount = $this->decimalOrNull($context['failed_amount']);
            $changed = true;
        }

        $latestFailedAt = $this->datetimeOrNull($context['latest_failed_at'] ?? null) ?? now();
        if (is_null($case->latest_failed_at) || $latestFailedAt->greaterThan($case->latest_failed_at)) {
            $case->latest_failed_at = $latestFailedAt;
            $changed = true;
        }

        if (is_null($case->first_failed_at)) {
            $case->first_failed_at = $this->datetimeOrNull($context['first_failed_at'] ?? null) ?? $latestFailedAt;
            $changed = true;
        }

        $normalizedCategory = $this->normalizeFailureCategory($context);
        $isRetryableCategory = $normalizedCategory !== null
            && app(FailedPaymentFailureCategoryMapper::class)->isRetryable($normalizedCategory);

        if (empty($case->failure_category)) {
            $case->failure_category = $normalizedCategory;
            $changed = true;
        } elseif (
            $case->current_status === FailedPaymentCaseStatus::UNMATCHED
            && $isRetryableCategory
            && $case->failure_category !== $normalizedCategory
        ) {
            $case->failure_category = $normalizedCategory;
            $changed = true;
        }

        $case->metadata = array_replace_recursive((array) ($case->metadata ?? []), (array) ($context['metadata'] ?? []));

        if ($case->current_status === FailedPaymentCaseStatus::UNMATCHED && (! empty($case->client_id) || ! empty($case->payment_history_id))) {
            $case->current_status = FailedPaymentCaseStatus::OPEN;
            $changed = true;
            $statusChanged = true;
        }

        if ($changed) {
            $case->save();
        } else {
            $case->saveQuietly();
        }

        if ($statusChanged && $previousStatus !== $case->current_status) {
            $this->recordStatusHistory($case, $previousStatus, $case->current_status, $context);
        }
    }

    protected function recordAttempt(FailedPaymentCase $case, array $context, bool $createdCase): bool
    {
        $idempotencyKey = $this->attemptIdempotencyKey($case, $context);

        if (FailedPaymentAttempt::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return false;
        }

        $attemptCount = (int) $case->attempts()->count() + 1;
        $status = ! empty($context['client_id']) || ! empty($context['payment_history_id'])
            ? FailedPaymentAttemptStatus::RECORDED
            : FailedPaymentAttemptStatus::UNMATCHED;

        FailedPaymentAttempt::create([
            'failed_payment_case_id' => $case->id,
            'anet_webhook_event_id' => $context['anet_webhook_event_id'] ?? null,
            'payment_history_id' => $context['payment_history_id'] ?? null,
            'attempt_number' => $attemptCount,
            'attempt_type' => $context['attempt_type'] ?? FailedPaymentAttemptType::WEBHOOK_FAILURE,
            'attempt_source' => $context['attempt_source'] ?? FailedPaymentSource::AUTHORIZE_NET_WEBHOOK,
            'scheduled_at' => $this->datetimeOrNull($context['scheduled_at'] ?? null),
            'started_at' => $this->datetimeOrNull($context['started_at'] ?? null),
            'completed_at' => $this->datetimeOrNull($context['completed_at'] ?? null) ?? now(),
            'amount' => $this->decimalOrNull($context['amount'] ?? $case->failed_amount),
            'status' => $status,
            'gateway_response_code' => $context['gateway_response_code'] ?? null,
            'gateway_response_message' => $context['gateway_response_message'] ?? null,
            'anet_transaction_id' => $context['anet_transaction_id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'is_automatic' => (bool) ($context['is_automatic'] ?? false),
            'metadata' => $context['metadata'] ?? [],
        ]);

        $case->attempt_count = $attemptCount;
        $case->save();

        return true;
    }

    protected function recordSystemAction(FailedPaymentCase $case, array $context): void
    {
        FailedPaymentAction::create([
            'failed_payment_case_id' => $case->id,
            'action_type' => FailedPaymentActionType::SYSTEM_EVENT,
            'action_source' => $context['action_source'] ?? FailedPaymentSource::AUTHORIZE_NET_WEBHOOK,
            'status' => FailedPaymentActionStatus::RECORDED,
            'scheduled_at' => null,
            'performed_at' => now(),
            'performed_by_user_id' => null,
            'recipient' => null,
            'template' => null,
            'provider_reference' => null,
            'failure_reason' => $context['failure_reason'] ?? null,
            'metadata' => $context['metadata'] ?? [],
        ]);
    }

    protected function recordStatusHistory(FailedPaymentCase $case, ?string $previousStatus, string $newStatus, array $context): void
    {
        FailedPaymentStatusHistory::create([
            'failed_payment_case_id' => $case->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'change_reason' => $context['change_reason'] ?? $context['failure_reason'] ?? null,
            'changed_by_type' => $context['changed_by_type'] ?? 'system',
            'changed_by_user_id' => $context['changed_by_user_id'] ?? $context['changed_by_id'] ?? null,
            'metadata' => $context['metadata'] ?? [],
            'created_at' => now(),
        ]);
    }

    protected function caseKey(array $context): ?string
    {
        $parts = [
            'client_id:'.(string) Arr::get($context, 'client_id', ''),
            'authorize_account_id:'.(string) Arr::get($context, 'authorize_account_id', ''),
            'payment_history_id:'.(string) Arr::get($context, 'payment_history_id', ''),
            'anet_webhook_event_id:'.(string) Arr::get($context, 'anet_webhook_event_id', ''),
            'claim_id:'.(string) Arr::get($context, 'claim_id', ''),
            'arb_subscription_id:'.trim((string) Arr::get($context, 'arb_subscription_id', '')),
            'anet_transaction_id:'.trim((string) Arr::get($context, 'anet_transaction_id', '')),
            'scheduled_payment_date:'.($this->dateOnly(Arr::get($context, 'scheduled_payment_date'))?->format('Y-m-d') ?? ''),
            'failed_amount:'.(string) ($this->decimalOrNull(Arr::get($context, 'failed_amount')) ?? ''),
            'currency:'.trim((string) Arr::get($context, 'currency', '')),
            'failure_code:'.trim((string) Arr::get($context, 'failure_code', '')),
            'payment_frequency:'.trim((string) Arr::get($context, 'payment_frequency', '')),
        ];

        $seed = trim(implode('|', $parts), '|');

        return $seed !== '' ? hash('sha256', $seed) : null;
    }

    protected function attemptIdempotencyKey(FailedPaymentCase $case, array $context): string
    {
        $seed = implode('|', [
            'case:'.$case->case_key,
            'event:'.(string) Arr::get($context, 'anet_webhook_event_id', ''),
            'payment_history:'.(string) Arr::get($context, 'payment_history_id', ''),
            'transaction:'.(string) Arr::get($context, 'anet_transaction_id', ''),
            'attempt_type:'.(string) Arr::get($context, 'attempt_type', FailedPaymentAttemptType::WEBHOOK_FAILURE),
            'source:'.(string) Arr::get($context, 'attempt_source', FailedPaymentSource::AUTHORIZE_NET_WEBHOOK),
            'amount:'.(string) $this->decimalOrNull(Arr::get($context, 'amount')),
            'failure_code:'.(string) Arr::get($context, 'failure_code', ''),
        ]);

        return hash('sha256', $seed);
    }

    protected function sanitizeContext(array $context): array
    {
        $metadata = (array) ($context['metadata'] ?? []);
        unset($metadata['raw'], $metadata['raw_payload'], $metadata['payload'], $metadata['full_card_number'], $metadata['cvv'], $metadata['signature_key'], $metadata['transaction_key'], $metadata['api_login_id']);

        $context['metadata'] = $metadata;
        $context['anet_webhook_event_id'] = $this->normalizeWebhookEventId($context['anet_webhook_event_id'] ?? null);
        $context['failure_reason'] = $context['failure_reason'] ?? null;
        $context['failure_category'] = $this->normalizeFailureCategory($context);
        $context['failure_source'] = $context['failure_source'] ?? FailedPaymentSource::AUTHORIZE_NET_WEBHOOK;
        $context['current_status'] = $context['current_status'] ?? null;

        return $context;
    }

    protected function normalizeFailureCategory(array $context): ?string
    {
        $provided = trim((string) Arr::get($context, 'failure_category', ''));
        if ($provided !== '') {
            return (new FailedPaymentFailureCategoryMapper)->normalize($provided);
        }

        return (new FailedPaymentFailureCategoryMapper)->map(
            Arr::get($context, 'failure_code'),
            Arr::get($context, 'failure_reason'),
            Arr::get($context, 'event_type')
        );
    }

    protected function normalizeWebhookEventId($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $event = AnetWebhookEvent::query()->find((int) $value);

            if ($event && ! empty($event->event_id)) {
                return (string) $event->event_id;
            }
        }

        return (string) $value;
    }

    protected function dateOnly($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function datetimeOrNull($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function decimalOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
