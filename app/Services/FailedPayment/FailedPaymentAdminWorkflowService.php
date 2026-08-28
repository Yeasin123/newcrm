<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentCaseStatus;
use App\Enums\FailedPaymentRetryExecutionIntent;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentCommunication;
use App\Models\FailedPaymentNote;
use App\Models\FailedPaymentRetrySchedule;
use App\Models\FailedPaymentStatusHistory;
use App\Services\FailedPayment\Contracts\FailedPaymentCommunicationGatewayInterface;
use App\Services\Telephony\SmsGateway;
use App\Services\Telephony\VoiceGateway;
use App\Services\Telephony\VoicemailGateway;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Admin actions on a failed-payment case: assignment, status transitions,
 * notes CRUD, retry-schedule control (pause/resume/cancel), retry simulation,
 * and communication preview / simulate / send.
 *
 * Ported near-verbatim from the legacy service. The one deliberate deviation:
 * live outbound delivery (dispatchLiveCommunication) depends on the telephony
 * stack (Telnyx / Twilio / JustDeliverit RVM) that lands in Phase 9, so the
 * live send path throws a clear "not yet wired" error. It is unreachable by
 * default anyway — assertLiveCommunicationEnabledForChannel gates it behind
 * failed_payments.{email,sms,voice,voicemail}_enabled, all of which default
 * to false. Preview and dry-run simulation are fully functional.
 */
class FailedPaymentAdminWorkflowService
{
    public function updateAssignment(FailedPaymentCase $case, ?int $assignedUserId, array $context = []): FailedPaymentCase
    {
        return DB::transaction(function () use ($case, $assignedUserId, $context) {
            $lockedCase = FailedPaymentCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $previousAssigned = $lockedCase->assigned_user_id;

            $lockedCase->assigned_user_id = $assignedUserId;
            $lockedCase->save();

            $this->recordAudit($lockedCase, [
                'action' => 'assignment_updated',
                'previous_status' => $lockedCase->current_status,
                'new_status' => $lockedCase->current_status,
                'metadata' => [
                    'previous_assigned_user_id' => $previousAssigned,
                    'new_assigned_user_id' => $assignedUserId,
                    'note' => Arr::get($context, 'note'),
                ],
                'notes' => $assignedUserId ? 'Failed-payment case assignment updated.' : 'Failed-payment case unassigned.',
                'source' => 'admin_assignment',
                'admin_id' => Arr::get($context, 'admin_id'),
            ]);

            return $lockedCase->refresh();
        });
    }

    public function transitionCase(FailedPaymentCase $case, string $action, array $context = []): FailedPaymentCase
    {
        return DB::transaction(function () use ($case, $action, $context) {
            $lockedCase = FailedPaymentCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $lockedCase->current_status;
            $now = Carbon::now();

            [$allowed, $newStatus, $reasonCode, $safeReason, $metadata] = $this->resolveCaseTransition($lockedCase, $action, $context);
            if (! $allowed) {
                throw new \RuntimeException($safeReason);
            }

            $lockedCase->current_status = $newStatus;
            if ($newStatus === FailedPaymentCaseStatus::RESOLVED) {
                $lockedCase->resolved_at = $lockedCase->resolved_at ?? $now;
                $lockedCase->next_action_at = null;
            }
            if ($newStatus === FailedPaymentCaseStatus::OPEN) {
                $lockedCase->resolved_at = null;
            }
            $lockedCase->metadata = array_replace_recursive((array) ($lockedCase->metadata ?? []), $metadata);
            $lockedCase->save();

            FailedPaymentStatusHistory::create([
                'failed_payment_case_id' => $lockedCase->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'change_reason' => Arr::get($context, 'reason') ?: $safeReason,
                'changed_by_type' => 'admin',
                'changed_by_user_id' => Arr::get($context, 'admin_id'),
                'metadata' => array_filter([
                    'action' => $action,
                    'reason_code' => $reasonCode,
                ], fn ($value) => ! is_null($value) && $value !== ''),
                'created_at' => $now,
            ]);

            $this->recordAudit($lockedCase, [
                'action' => $action,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'metadata' => [
                    'reason_code' => $reasonCode,
                    'transition' => 'case_status',
                ],
                'notes' => $safeReason,
                'source' => 'admin_case_status',
                'admin_id' => Arr::get($context, 'admin_id'),
            ]);

            return $lockedCase->refresh();
        });
    }

    public function createNote(FailedPaymentCase $case, array $data, array $context = []): FailedPaymentNote
    {
        return DB::transaction(function () use ($case, $data, $context) {
            $lockedCase = FailedPaymentCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $note = FailedPaymentNote::create([
                'failed_payment_case_id' => $lockedCase->id,
                'note_body' => $this->normalizeNoteBody((string) ($data['note_body'] ?? '')),
                'note_type' => $this->normalizeNoteType($data['note_type'] ?? 'internal'),
                'created_by' => Arr::get($context, 'admin_id'),
                'metadata' => array_filter([
                    'source' => 'admin_note_create',
                ], fn ($value) => ! is_null($value) && $value !== ''),
            ]);

            $this->recordAudit($lockedCase, [
                'action' => 'note_created',
                'previous_status' => $lockedCase->current_status,
                'new_status' => $lockedCase->current_status,
                'metadata' => [
                    'note_id' => $note->id,
                    'note_type' => $note->note_type,
                ],
                'notes' => 'Failed-payment note created.',
                'source' => 'admin_notes',
                'admin_id' => Arr::get($context, 'admin_id'),
            ]);

            return $note;
        });
    }

    public function updateNote(FailedPaymentNote $note, array $data, array $context = []): FailedPaymentNote
    {
        return DB::transaction(function () use ($note, $data, $context) {
            $lockedNote = FailedPaymentNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            $lockedCase = FailedPaymentCase::query()->whereKey($lockedNote->failed_payment_case_id)->lockForUpdate()->firstOrFail();

            $lockedNote->note_body = $this->normalizeNoteBody((string) ($data['note_body'] ?? $lockedNote->note_body));
            $lockedNote->note_type = $this->normalizeNoteType($data['note_type'] ?? $lockedNote->note_type);
            $lockedNote->metadata = array_replace_recursive((array) ($lockedNote->metadata ?? []), [
                'updated_by' => Arr::get($context, 'admin_id'),
            ]);
            $lockedNote->save();

            $this->recordAudit($lockedCase, [
                'action' => 'note_updated',
                'previous_status' => $lockedCase->current_status,
                'new_status' => $lockedCase->current_status,
                'metadata' => [
                    'note_id' => $lockedNote->id,
                    'note_type' => $lockedNote->note_type,
                ],
                'notes' => 'Failed-payment note updated.',
                'source' => 'admin_notes',
                'admin_id' => Arr::get($context, 'admin_id'),
            ]);

            return $lockedNote->refresh();
        });
    }

    public function deleteNote(FailedPaymentNote $note, array $context = []): void
    {
        DB::transaction(function () use ($note, $context) {
            $lockedNote = FailedPaymentNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            $lockedCase = FailedPaymentCase::query()->whereKey($lockedNote->failed_payment_case_id)->lockForUpdate()->firstOrFail();

            $lockedNote->delete();

            $this->recordAudit($lockedCase, [
                'action' => 'note_deleted',
                'previous_status' => $lockedCase->current_status,
                'new_status' => $lockedCase->current_status,
                'metadata' => [
                    'note_id' => $lockedNote->id,
                    'note_type' => $lockedNote->note_type,
                ],
                'notes' => 'Failed-payment note deleted.',
                'source' => 'admin_notes',
                'admin_id' => Arr::get($context, 'admin_id'),
            ]);
        });
    }

    public function controlRetry(FailedPaymentRetrySchedule $schedule, string $action, array $context = []): FailedPaymentRetrySchedule
    {
        return DB::transaction(function () use ($schedule, $action, $context) {
            $lockedSchedule = FailedPaymentRetrySchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            $lockedCase = FailedPaymentCase::query()->whereKey($lockedSchedule->failed_payment_case_id)->lockForUpdate()->firstOrFail();

            [$allowed, $newStatus, $reasonCode, $safeReason] = $this->resolveRetryTransition($lockedSchedule, $action);
            if (! $allowed) {
                throw new \RuntimeException($safeReason);
            }

            $previousStatus = $lockedSchedule->status;
            $lockedSchedule->status = $newStatus;
            $lockedSchedule->metadata = array_replace_recursive((array) ($lockedSchedule->metadata ?? []), [
                'admin_action' => $action,
                'admin_reason_code' => $reasonCode,
                'updated_by' => Arr::get($context, 'admin_id'),
            ]);
            $lockedSchedule->completed_at = in_array($newStatus, [
                FailedPaymentRetryScheduleStatus::CANCELLED,
            ], true) ? Carbon::now() : $lockedSchedule->completed_at;
            $lockedSchedule->save();

            $this->recordAudit($lockedCase, [
                'action' => 'retry_'.$action,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'metadata' => [
                    'retry_schedule_id' => $lockedSchedule->id,
                    'previous_retry_status' => $previousStatus,
                    'new_retry_status' => $newStatus,
                    'reason_code' => $reasonCode,
                ],
                'notes' => $safeReason,
                'source' => 'admin_retry_control',
                'admin_id' => Arr::get($context, 'admin_id'),
            ]);

            return $lockedSchedule->refresh();
        });
    }

    public function simulateRetry(FailedPaymentRetrySchedule $schedule, array $context = []): array
    {
        return DB::transaction(function () use ($schedule, $context) {
            $lockedSchedule = FailedPaymentRetrySchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            $lockedCase = FailedPaymentCase::query()->whereKey($lockedSchedule->failed_payment_case_id)->lockForUpdate()->firstOrFail();

            $processor = app(FailedPaymentRetryProcessorService::class);
            $result = $processor->processSchedule($lockedSchedule, array_merge($context, [
                'execution_intent' => FailedPaymentRetryExecutionIntent::SIMULATION,
            ]));

            // processSchedule() ran in a nested transaction on its own model
            // instance and wrote last_simulation_key / last_attempt_result onto
            // the schedule metadata. Re-read here so the audit-key merge below
            // does not save this stale copy back over the processor's write.
            $lockedSchedule = $lockedSchedule->fresh() ?? $lockedSchedule;

            $auditKey = hash('sha256', implode('|', [
                'admin_retry_simulation',
                $lockedSchedule->id,
                (string) Arr::get((array) ($lockedSchedule->metadata ?? []), 'last_simulation_key', ''),
                (string) Arr::get((array) ($lockedSchedule->metadata ?? []), 'last_execution_intent', ''),
                (string) $lockedSchedule->status,
            ]));
            $alreadyAudited = (string) Arr::get((array) ($lockedSchedule->metadata ?? []), 'last_admin_simulation_audit_key', '') === $auditKey;

            if (($result['status'] ?? null) !== 'duplicate_simulation' && ! $alreadyAudited) {
                $this->recordAudit($lockedCase, [
                    'action' => 'retry_simulated',
                    'previous_status' => $lockedSchedule->status,
                    'new_status' => $lockedSchedule->status,
                    'metadata' => array_filter([
                        'retry_schedule_id' => $lockedSchedule->id,
                        'simulation_status' => $result['status'] ?? null,
                        'simulation_attempt_result' => $result['attempt_result'] ?? null,
                    ], fn ($value) => ! is_null($value) && $value !== ''),
                    'notes' => 'Retry simulation completed without a live charge.',
                    'source' => 'admin_retry_simulation',
                    'admin_id' => Arr::get($context, 'admin_id'),
                ]);

                $lockedSchedule->metadata = array_replace_recursive((array) ($lockedSchedule->metadata ?? []), [
                    'last_admin_simulation_audit_key' => $auditKey,
                ]);
                $lockedSchedule->save();
            }

            return [
                'schedule' => $lockedSchedule->refresh(),
                'result' => $result,
            ];
        });
    }

    public function previewCommunication(FailedPaymentCase $case, array $context = []): array
    {
        $case->loadMissing('client');
        $payload = $this->prepareCommunicationPayload($case, $context);

        return array_merge($payload, [
            'status' => 'preview',
            'delivery_mode' => 'no_delivery',
            'simulation_key' => $this->buildCommunicationSimulationKey($case, $payload),
            'provider_reference' => null,
            'communication_id' => null,
            'execution_intent' => 'preview',
        ]);
    }

    public function simulateCommunication(FailedPaymentCase $case, array $context = []): FailedPaymentCommunicationResult
    {
        return DB::transaction(function () use ($case, $context) {
            $lockedCase = FailedPaymentCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $lockedCase->loadMissing('client');

            $payload = $this->prepareCommunicationPayload($lockedCase, $context);
            $simulationKey = $this->buildCommunicationSimulationKey($lockedCase, $payload);
            $providerReference = $this->communicationProviderReference($simulationKey);

            $existing = FailedPaymentCommunication::query()
                ->where('failed_payment_case_id', $lockedCase->id)
                ->where('provider_reference', $providerReference)
                ->first();

            if ($existing) {
                return $this->communicationResultFromModel($existing, $payload, $simulationKey, 'duplicate');
            }

            $gateway = app(FailedPaymentCommunicationGatewayInterface::class);
            $gatewayResult = $gateway->simulate(array_merge($payload, [
                'simulation_key' => $simulationKey,
                'provider_reference' => $providerReference,
                'metadata' => [
                    'execution_intent' => 'simulation',
                ],
            ]));

            if ($gatewayResult->status !== 'simulated') {
                throw new \RuntimeException($gatewayResult->message ?: 'Communication simulation is unavailable.');
            }

            $now = Carbon::now();
            $communication = FailedPaymentCommunication::create([
                'failed_payment_case_id' => $lockedCase->id,
                'channel' => $payload['channel'],
                'recipient' => $payload['recipient'],
                'template_name' => $payload['template_id'],
                'subject' => $payload['subject'],
                'sanitized_message' => $payload['message'],
                'provider_reference' => $providerReference,
                'status' => 'simulated',
                'queued_at' => null,
                'sent_at' => null,
                'delivered_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
                'initiated_by' => 'admin_simulation',
                'metadata' => array_merge($payload['metadata'], [
                    'execution_intent' => 'simulation',
                    'delivery_mode' => 'no_delivery',
                    'simulation_key' => $simulationKey,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->recordCommunicationAudit($lockedCase, $communication, $payload, $simulationKey);

            return $this->communicationResultFromModel($communication, $payload, $simulationKey, 'created');
        });
    }

    public function sendCommunication(FailedPaymentCase $case, array $context = []): FailedPaymentCommunicationResult
    {
        $payload = $this->prepareCommunicationPayload($case, $context);
        $channel = (string) $payload['channel'];
        $this->assertLiveCommunicationEnabledForChannel($channel);

        $operationKey = $this->buildCommunicationOperationKey($case, $payload, 'live');
        $providerReference = $this->communicationProviderReference($operationKey, 'live');

        $existing = FailedPaymentCommunication::query()
            ->where('failed_payment_case_id', $case->id)
            ->where('provider_reference', $providerReference)
            ->first();

        if ($existing) {
            return $this->communicationResultFromModel($existing, $payload, $operationKey, 'duplicate');
        }

        $creation = DB::transaction(function () use ($case, $payload, $providerReference) {
            $lockedCase = FailedPaymentCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $lockedCase->loadMissing('client');

            $existing = FailedPaymentCommunication::query()
                ->where('failed_payment_case_id', $lockedCase->id)
                ->where('provider_reference', $providerReference)
                ->first();

            if ($existing) {
                return [
                    'communication_id' => $existing->id,
                    'duplicate' => true,
                ];
            }

            $now = Carbon::now();
            $communication = FailedPaymentCommunication::create([
                'failed_payment_case_id' => $lockedCase->id,
                'channel' => $payload['channel'],
                'recipient' => $payload['recipient'],
                'template_name' => $payload['template_id'],
                'subject' => $payload['subject'],
                'sanitized_message' => $payload['message'],
                'provider_reference' => $providerReference,
                'status' => 'sending',
                'queued_at' => $now,
                'sent_at' => null,
                'delivered_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
                'initiated_by' => 'admin_manual_send',
                'metadata' => array_merge($payload['metadata'], [
                    'execution_intent' => 'live',
                    'delivery_mode' => 'live',
                    'operation_key' => $providerReference,
                    'automation_key' => $payload['automation_key'] ?? null,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'communication_id' => $communication->id,
                'duplicate' => false,
            ];
        });

        if (($creation['duplicate'] ?? false) === true) {
            $existing = FailedPaymentCommunication::query()->findOrFail($creation['communication_id']);

            return $this->communicationResultFromModel($existing, $payload, $providerReference, 'duplicate');
        }

        try {
            $dispatchResult = $this->dispatchLiveCommunication($case, $payload);
            $finalStatus = (string) ($dispatchResult['status'] ?? 'sent');
            $failureReason = null;
        } catch (\Throwable $e) {
            $dispatchResult = [
                'status' => 'failed',
                'provider_reference' => $providerReference,
                'provider_response' => null,
                'delivery_mode' => 'live',
            ];
            $finalStatus = 'failed';
            $failureReason = $this->safeText($e->getMessage()) ?: 'Manual live communication failed.';
        }

        return DB::transaction(function () use ($case, $payload, $creation, $providerReference, $dispatchResult, $finalStatus, $failureReason) {
            $communication = FailedPaymentCommunication::query()
                ->whereKey($creation['communication_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $now = Carbon::now();
            $communication->fill([
                'status' => $finalStatus,
                'sent_at' => in_array($finalStatus, ['sent', 'delivered'], true) ? ($communication->sent_at ?? $now) : null,
                'delivered_at' => $finalStatus === 'delivered' ? ($communication->delivered_at ?? $now) : null,
                'failed_at' => $finalStatus === 'failed' ? $now : null,
                'failure_reason' => $failureReason,
                'metadata' => array_merge((array) ($communication->metadata ?? []), [
                    'execution_intent' => 'live',
                    'delivery_mode' => 'live',
                    'dispatch_result' => array_filter([
                        'status' => $dispatchResult['status'] ?? null,
                        'provider_reference' => $dispatchResult['provider_reference'] ?? null,
                    ], fn ($value) => ! is_null($value) && $value !== ''),
                ]),
                'updated_at' => $now,
            ])->save();

            $this->recordCommunicationAudit(
                $case,
                $communication,
                $payload,
                $providerReference,
                $finalStatus === 'failed' ? 'communication_failed' : 'communication_sent',
                'admin_manual_communication',
                $finalStatus === 'failed'
                    ? 'Failed-payment communication could not be delivered live.'
                    : 'Failed-payment communication delivered live.'
            );

            return $this->communicationResultFromModel($communication->refresh(), $payload, $providerReference, $finalStatus);
        });
    }

    protected function resolveCaseTransition(FailedPaymentCase $case, string $action, array $context): array
    {
        $current = (string) $case->current_status;
        $retryState = (string) Arr::get((array) ($case->metadata ?? []), 'retry_state', '');

        return match ($action) {
            'manually_resolved' => $this->transitionResult(
                in_array($current, [FailedPaymentCaseStatus::NEW, FailedPaymentCaseStatus::OPEN, FailedPaymentCaseStatus::UNDER_REVIEW, FailedPaymentCaseStatus::UNMATCHED], true),
                FailedPaymentCaseStatus::RESOLVED,
                'manual_resolution',
                'Manual resolution is only allowed from an unresolved case.',
                ['retry_state' => 'manual_resolution']
            ),
            'ignored' => $this->transitionResult(
                in_array($current, [FailedPaymentCaseStatus::NEW, FailedPaymentCaseStatus::OPEN, FailedPaymentCaseStatus::UNDER_REVIEW, FailedPaymentCaseStatus::UNMATCHED], true),
                FailedPaymentCaseStatus::CANCELLED,
                'ignored',
                'Ignore is only allowed from an unresolved case.',
                ['retry_state' => 'ignored']
            ),
            'reopen' => $this->resolveReopenTransition($case, $retryState),
            default => [false, $current, 'invalid_action', 'The requested case action is not supported.', []],
        };
    }

    protected function resolveReopenTransition(FailedPaymentCase $case, string $retryState): array
    {
        if ($case->current_status === FailedPaymentCaseStatus::RECOVERED) {
            return [false, $case->current_status, 'case_recovered', 'Recovered cases cannot be reopened through the generic action.', []];
        }

        $allowed = in_array($case->current_status, [FailedPaymentCaseStatus::RESOLVED, FailedPaymentCaseStatus::CANCELLED], true)
            && in_array($retryState, ['manual_resolution', 'ignored'], true);

        return $this->transitionResult(
            $allowed,
            FailedPaymentCaseStatus::OPEN,
            'reopen',
            'Reopen is only allowed for manually resolved or ignored cases.',
            ['retry_state' => 'open', 'reopened_from' => $retryState]
        );
    }

    protected function resolveRetryTransition(FailedPaymentRetrySchedule $schedule, string $action): array
    {
        $status = (string) $schedule->status;

        return match ($action) {
            'pause' => $this->transitionResult(
                $status === FailedPaymentRetryScheduleStatus::PENDING,
                FailedPaymentRetryScheduleStatus::PAUSED,
                'retry_paused',
                'Only pending retry schedules can be paused.'
            ),
            'resume' => $this->transitionResult(
                $status === FailedPaymentRetryScheduleStatus::PAUSED,
                FailedPaymentRetryScheduleStatus::PENDING,
                'retry_resumed',
                'Only paused retry schedules can be resumed.'
            ),
            'cancel' => $this->transitionResult(
                in_array($status, [FailedPaymentRetryScheduleStatus::PENDING, FailedPaymentRetryScheduleStatus::PAUSED], true),
                FailedPaymentRetryScheduleStatus::CANCELLED,
                'retry_cancelled',
                'Only pending or paused retry schedules can be cancelled.'
            ),
            default => [false, $status, 'invalid_action', 'The requested retry control action is not supported.'],
        };
    }

    protected function transitionResult(bool $allowed, string $newStatus, string $reasonCode, string $safeReason, array $metadata = []): array
    {
        return [
            $allowed,
            $newStatus,
            $reasonCode,
            $safeReason,
            array_merge(['requested_status' => $newStatus], $metadata),
        ];
    }

    protected function normalizeNoteBody(string $noteBody): string
    {
        $noteBody = trim(strip_tags($noteBody));

        return Str::limit($noteBody, 4000, '');
    }

    protected function normalizeNoteType(string $noteType): string
    {
        $noteType = strtolower(trim($noteType));

        return $noteType !== '' ? preg_replace('/[^a-z0-9_-]/', '', $noteType) ?: 'internal' : 'internal';
    }

    protected function safeText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function recordAudit(FailedPaymentCase $case, array $payload): void
    {
        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => $case->id,
            'previous_status' => $payload['previous_status'] ?? null,
            'new_status' => $payload['new_status'] ?? null,
            'action' => $payload['action'] ?? 'admin_action',
            'source' => $payload['source'] ?? 'admin_workflow',
            'admin_id' => $payload['admin_id'] ?? null,
            'ip_address' => request()?->ip(),
            'safe_request_metadata' => $payload['safe_request_metadata'] ?? [],
            'notes' => $payload['notes'] ?? null,
            'metadata' => array_filter((array) ($payload['metadata'] ?? []), fn ($value) => ! is_null($value) && $value !== ''),
        ]);
    }

    protected function prepareCommunicationPayload(FailedPaymentCase $case, array $context): array
    {
        $channel = strtolower(trim((string) Arr::get($context, 'channel', 'email')));
        $retryStage = strtolower(trim((string) Arr::get($context, 'retry_stage', 'manual')));

        $catalog = app(FailedPaymentCommunicationTemplateCatalog::class);
        $templateId = strtolower(trim((string) Arr::get($context, 'template', $catalog->defaultTemplateIdForChannel($channel) ?? '')));
        $allowedTemplates = $catalog->allowedTemplateIdsForChannel($channel);

        if (! in_array($channel, $catalog->channels(), true) || ! in_array($templateId, $allowedTemplates, true)) {
            throw new \RuntimeException('Unsupported failed-payment communication template.');
        }

        $recipient = $this->resolveCommunicationRecipient($case, $channel);
        $recipient = $this->validateCommunicationRecipient($channel, $recipient);

        $rendered = $catalog->render($case, $channel, $templateId, [
            'recipient' => $recipient,
            'masked_recipient' => $catalog->maskRecipient($recipient),
            'retry_stage' => $retryStage,
            'retry_stage_label' => Arr::get($context, 'retry_stage_label'),
            'retry_date' => Arr::get($context, 'retry_date'),
        ]);

        return array_merge($rendered, [
            'retry_stage' => $retryStage,
            'automation_key' => (string) Arr::get($context, 'automation_key', ''),
            'recipient_identity' => $this->normalizedRecipientIdentity($channel, $recipient),
        ]);
    }

    protected function resolveCommunicationRecipient(FailedPaymentCase $case, string $channel): ?string
    {
        $metadata = (array) ($case->metadata ?? []);
        $channel = strtolower(trim($channel));

        $candidates = match ($channel) {
            'email' => [
                Arr::get($metadata, 'customer_email_snapshot'),
                Arr::get($metadata, 'email_snapshot'),
                $case->client?->email,
            ],
            'sms', 'voice', 'voicemail' => [
                Arr::get($metadata, 'customer_phone_snapshot'),
                Arr::get($metadata, 'phone_snapshot'),
                $case->client?->contact,
            ],
            default => [],
        };

        foreach ($candidates as $candidate) {
            $candidate = $this->safeRecipientValue($candidate);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    protected function safeRecipientValue($value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value !== '' ? $value : null;
    }

    protected function validateCommunicationRecipient(string $channel, ?string $recipient): string
    {
        $recipient = $this->safeRecipientValue($recipient);
        $channel = strtolower(trim($channel));

        if ($recipient === null) {
            throw new \RuntimeException('A safe recipient could not be resolved for the selected channel.');
        }

        if ($channel === 'email') {
            $normalized = strtolower(trim($recipient));

            if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('A safe recipient could not be resolved for the selected channel.');
            }

            return $normalized;
        }

        if (in_array($channel, ['sms', 'voice', 'voicemail'], true)) {
            $digits = preg_replace('/\D+/', '', $recipient) ?? '';

            if ($digits === '') {
                throw new \RuntimeException('A safe recipient could not be resolved for the selected channel.');
            }

            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = substr($digits, 1);
            }

            if (strlen($digits) !== 10) {
                throw new \RuntimeException('A safe recipient could not be resolved for the selected channel.');
            }

            return $digits;
        }

        throw new \RuntimeException('A safe recipient could not be resolved for the selected channel.');
    }

    protected function normalizedRecipientIdentity(string $channel, string $recipient): string
    {
        $recipient = $this->safeRecipientValue($recipient) ?? '';

        if ($recipient === '') {
            return '';
        }

        if ($channel === 'email') {
            return strtolower($recipient);
        }

        return preg_replace('/\D+/', '', $recipient) ?? $recipient;
    }

    protected function buildCommunicationSimulationKey(FailedPaymentCase $case, array $payload): string
    {
        return hash('sha256', implode('|', [
            'failed_payment_communication',
            'simulation',
            $case->id,
            $payload['channel'] ?? '',
            $payload['template_id'] ?? '',
            $payload['template_version'] ?? 1,
            $payload['retry_stage'] ?? 'manual',
            $payload['automation_key'] ?? '',
            $payload['recipient_identity'] ?? '',
        ]));
    }

    protected function communicationProviderReference(string $simulationKey, string $prefix = 'sim'): string
    {
        return $prefix.':'.$simulationKey;
    }

    protected function buildCommunicationOperationKey(FailedPaymentCase $case, array $payload, string $executionIntent): string
    {
        return hash('sha256', implode('|', [
            'failed_payment_communication',
            $executionIntent,
            $case->id,
            $payload['channel'] ?? '',
            $payload['template_id'] ?? '',
            $payload['template_version'] ?? 1,
            $payload['retry_stage'] ?? 'manual',
            $payload['automation_key'] ?? '',
            $payload['recipient_identity'] ?? '',
        ]));
    }

    protected function assertLiveCommunicationEnabledForChannel(string $channel): void
    {
        $channel = strtolower(trim($channel));

        $enabled = match ($channel) {
            'email' => (bool) config('failed_payments.email_enabled'),
            'sms' => (bool) config('failed_payments.sms_enabled'),
            'voice' => (bool) config('failed_payments.voice_enabled'),
            'voicemail' => (bool) config('failed_payments.voicemail_enabled'),
            default => false,
        };

        if (! $enabled) {
            Log::warning('Failed-payment live delivery blocked because the channel is disabled.', [
                'channel' => $channel,
                'email_enabled' => (bool) config('failed_payments.email_enabled'),
                'sms_enabled' => (bool) config('failed_payments.sms_enabled'),
                'voice_enabled' => (bool) config('failed_payments.voice_enabled'),
                'voicemail_enabled' => (bool) config('failed_payments.voicemail_enabled'),
            ]);

            throw new \RuntimeException('Live delivery is disabled for the selected channel.');
        }
    }

    /**
     * Live outbound delivery through the Phase 9f telephony gateways. Reachable
     * only once an admin flips failed_payments.{channel}_enabled (default
     * false); each gateway is additionally Null-bound unless its own
     * config/telephony.php flag is on. A gateway failure throws so the caller
     * records the communication as failed.
     */
    protected function dispatchLiveCommunication(FailedPaymentCase $case, array $payload): array
    {
        $channel = (string) ($payload['channel'] ?? '');
        $recipient = (string) ($payload['recipient'] ?? '');
        $message = (string) ($payload['message'] ?? '');
        $audioUrl = (string) ($payload['audio_url'] ?? '');

        return match ($channel) {
            'email' => $this->dispatchLiveEmail($recipient, (string) ($payload['subject'] ?? 'Payment update'), $message),
            'sms' => $this->fromGatewayResult(app(SmsGateway::class)->send($recipient, $message)),
            'voice' => $this->fromGatewayResult(app(VoiceGateway::class)->call($recipient, $message, $audioUrl ?: null)),
            'voicemail' => $this->fromGatewayResult(app(VoicemailGateway::class)->drop($recipient, $audioUrl, 'FP Case #'.$case->id)),
            default => throw new \RuntimeException('Unsupported failed-payment communication channel.'),
        };
    }

    protected function dispatchLiveEmail(string $to, string $subject, string $body): array
    {
        Mail::raw($body, function ($mail) use ($to, $subject) {
            $mail->to($to)->subject($subject);

            if ($from = config('mail.from.address')) {
                $mail->from($from, config('mail.from.name') ?: null);
                $mail->replyTo($from, config('mail.from.name') ?: null);
            }
        });

        return ['status' => 'sent', 'provider_reference' => null, 'delivery_mode' => 'live'];
    }

    /** @param  array{success:bool, message_id?:?string, provider_reference?:?string, error?:?string}  $result */
    protected function fromGatewayResult(array $result): array
    {
        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException($result['error'] ?? 'Live delivery failed.');
        }

        return [
            'status' => 'sent',
            'provider_reference' => $result['message_id'] ?? $result['provider_reference'] ?? null,
            'delivery_mode' => 'live',
        ];
    }

    protected function communicationResultFromModel(FailedPaymentCommunication $communication, array $payload, string $simulationKey, string $status): FailedPaymentCommunicationResult
    {
        return new FailedPaymentCommunicationResult(
            status: (string) $communication->status,
            channel: (string) $communication->channel,
            templateId: (string) $communication->template_name,
            templateVersion: (int) data_get($communication->metadata, 'template_version', $payload['template_version'] ?? 1),
            subject: $communication->subject,
            message: (string) $communication->sanitized_message,
            recipient: (string) $communication->recipient,
            maskedRecipient: app(FailedPaymentCommunicationTemplateCatalog::class)->maskRecipient((string) $communication->recipient),
            simulationKey: $simulationKey,
            providerReference: (string) $communication->provider_reference,
            communicationId: $communication->id,
            failureReason: $this->safeText((string) ($communication->failure_reason ?? '')) ?: null,
            metadata: array_merge((array) ($communication->metadata ?? []), [
                'record_state' => $status,
            ]),
        );
    }

    protected function recordCommunicationAudit(FailedPaymentCase $case, FailedPaymentCommunication $communication, array $payload, string $simulationKey, string $action = 'communication_simulated', string $source = 'admin_communication_simulation', ?string $notes = null): void
    {
        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => $case->id,
            'previous_status' => $case->current_status,
            'new_status' => $case->current_status,
            'action' => $action,
            'source' => $source,
            'admin_id' => Auth::guard('web')->id(),
            'ip_address' => request()?->ip(),
            'safe_request_metadata' => array_filter([
                'communication_id' => $communication->id,
                'channel' => $payload['channel'],
                'template_id' => $payload['template_id'],
                'template_version' => $payload['template_version'],
                'retry_stage' => $payload['retry_stage'],
                'simulation_key' => $simulationKey,
                'recipient_masked' => $payload['masked_recipient'],
            ], fn ($value) => ! is_null($value) && $value !== ''),
            'notes' => $notes ?: 'Failed-payment communication simulated without delivery.',
            'metadata' => [
                'delivery_mode' => 'no_delivery',
                'execution_intent' => 'simulation',
            ],
        ]);
    }
}
