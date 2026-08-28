<?php

namespace App\Livewire\FailedPayments;

use App\Enums\FailedPaymentCaseStatus;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentNote;
use App\Models\FailedPaymentRetrySchedule;
use App\Models\User;
use App\Services\FailedPayment\FailedPaymentAdminWorkflowService;
use App\Services\FailedPayment\FailedPaymentCommunicationTemplateCatalog;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Failed-payment case detail. Ported from FailedPaymentController@show +
 * FailedPaymentWorkflowController — every mutation is delegated to
 * FailedPaymentAdminWorkflowService (locks, audit rows, status history).
 */
class Show extends Component
{
    public FailedPaymentCase $case;

    // assignment / status
    public ?int $assigned_user_id = null;

    // note composer
    public string $noteBody = '';

    public string $noteType = 'internal';

    public ?int $editingNoteId = null;

    // communication composer
    public string $commChannel = 'email';

    public string $commTemplate = '';

    public string $commRetryStage = 'manual';

    public ?array $commPreview = null;

    /** Summary of the most recent retry simulation, for the operator. */
    public ?array $lastSimResult = null;

    public function mount(string $failedPaymentCase, FailedPaymentCommunicationTemplateCatalog $catalog): void
    {
        abort_unless($this->moduleEnabled(), 404);

        $this->case = FailedPaymentCase::query()
            ->where('case_uuid', $failedPaymentCase)
            ->firstOrFail();

        Gate::authorize('view', $this->case);

        $this->assigned_user_id = $this->case->assigned_user_id;
        $this->commTemplate = $catalog->defaultTemplateIdForChannel($this->commChannel) ?? '';
    }

    protected function moduleEnabled(): bool
    {
        return (bool) config('failed_payments.module_enabled') && (bool) config('failed_payments.admin_enabled');
    }

    protected function service(): FailedPaymentAdminWorkflowService
    {
        return app(FailedPaymentAdminWorkflowService::class);
    }

    protected function context(): array
    {
        return ['admin_id' => auth()->id()];
    }

    /* ---- assignment ---- */

    public function saveAssignment(): void
    {
        Gate::authorize('assign', $this->case);

        $this->case = $this->service()->updateAssignment(
            $this->case,
            $this->assigned_user_id ?: null,
            $this->context(),
        );

        Flux::toast(variant: 'success', text: __('Assignment updated.'));
    }

    /* ---- status transitions ---- */

    public function transitionCase(string $action): void
    {
        Gate::authorize('update', $this->case);

        try {
            $this->case = $this->service()->transitionCase($this->case, $action, $this->context());
            Flux::toast(variant: 'success', text: __('Case :action.', ['action' => str_replace('_', ' ', $action)]));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /* ---- notes ---- */

    public function addNote(): void
    {
        Gate::authorize('notes', $this->case);

        $this->validate([
            'noteBody' => ['required', 'string', 'max:4000'],
            'noteType' => ['required', 'string', 'max:50'],
        ]);

        if ($this->editingNoteId) {
            $note = FailedPaymentNote::where('failed_payment_case_id', $this->case->id)
                ->findOrFail($this->editingNoteId);
            $this->service()->updateNote($note, [
                'note_body' => $this->noteBody,
                'note_type' => $this->noteType,
            ], $this->context());
            Flux::toast(variant: 'success', text: __('Note updated.'));
        } else {
            $this->service()->createNote($this->case, [
                'note_body' => $this->noteBody,
                'note_type' => $this->noteType,
            ], $this->context());
            Flux::toast(variant: 'success', text: __('Note added.'));
        }

        $this->reset(['noteBody', 'editingNoteId']);
        $this->noteType = 'internal';
        $this->case->refresh();
    }

    public function editNote(int $noteId): void
    {
        $note = FailedPaymentNote::where('failed_payment_case_id', $this->case->id)->findOrFail($noteId);
        $this->editingNoteId = $note->id;
        $this->noteBody = (string) $note->note_body;
        $this->noteType = (string) $note->note_type;
    }

    public function cancelNoteEdit(): void
    {
        $this->reset(['noteBody', 'editingNoteId']);
        $this->noteType = 'internal';
    }

    public function deleteNote(int $noteId): void
    {
        Gate::authorize('notes', $this->case);

        $note = FailedPaymentNote::where('failed_payment_case_id', $this->case->id)->findOrFail($noteId);
        $this->service()->deleteNote($note, $this->context());
        $this->case->refresh();
        Flux::toast(variant: 'success', text: __('Note deleted.'));
    }

    /* ---- retry schedule control ---- */

    public function controlRetry(int $scheduleId, string $action): void
    {
        Gate::authorize('controlRetry', $this->case);

        $schedule = FailedPaymentRetrySchedule::where('failed_payment_case_id', $this->case->id)->findOrFail($scheduleId);

        try {
            $this->service()->controlRetry($schedule, $action, $this->context());
            Flux::toast(variant: 'success', text: __('Retry schedule :action.', ['action' => $action.'d']));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->case->refresh();
    }

    public function simulateRetry(int $scheduleId): void
    {
        Gate::authorize('simulateRetry', $this->case);

        $schedule = FailedPaymentRetrySchedule::where('failed_payment_case_id', $this->case->id)->findOrFail($scheduleId);

        try {
            $out = $this->service()->simulateRetry($schedule, $this->context());
            $result = $out['result'] ?? [];
            $this->lastSimResult = [
                'schedule_id' => $scheduleId,
                'status' => $result['status'] ?? 'done',
                'attempt_result' => $result['attempt_result'] ?? '—',
                'safe_reason' => $result['safe_reason'] ?? null,
            ];
            Flux::toast(
                variant: 'success',
                text: __('Simulation: :status (:result)', [
                    'status' => $this->lastSimResult['status'],
                    'result' => $this->lastSimResult['attempt_result'],
                ]),
            );
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->case->refresh();
    }

    /* ---- communications ---- */

    public function updatedCommChannel(FailedPaymentCommunicationTemplateCatalog $catalog): void
    {
        $this->commTemplate = $catalog->defaultTemplateIdForChannel($this->commChannel) ?? '';
        $this->commPreview = null;
    }

    public function previewCommunication(): void
    {
        Gate::authorize('communications', $this->case);

        try {
            $payload = $this->service()->previewCommunication($this->case, [
                'channel' => $this->commChannel,
                'template' => $this->commTemplate,
                'retry_stage' => $this->commRetryStage,
            ]);
            $this->commPreview = [
                'subject' => $payload['subject'] ?? null,
                'message' => $payload['message'] ?? '',
                'masked_recipient' => $payload['masked_recipient'] ?? null,
                'channel' => $payload['channel'] ?? $this->commChannel,
            ];
        } catch (\Throwable $e) {
            $this->commPreview = null;
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function simulateCommunication(): void
    {
        Gate::authorize('communications', $this->case);

        try {
            $result = $this->service()->simulateCommunication($this->case, [
                'channel' => $this->commChannel,
                'template' => $this->commTemplate,
                'retry_stage' => $this->commRetryStage,
            ]);
            Flux::toast(variant: 'success', text: __('Dry-run communication recorded (:state).', ['state' => $result->status]));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->case->refresh();
    }

    public function sendCommunication(): void
    {
        Gate::authorize('communications', $this->case);

        try {
            $this->service()->sendCommunication($this->case, [
                'channel' => $this->commChannel,
                'template' => $this->commTemplate,
                'retry_stage' => $this->commRetryStage,
            ]);
            Flux::toast(variant: 'success', text: __('Live communication dispatched.'));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->case->refresh();
    }

    public function render(FailedPaymentCommunicationTemplateCatalog $catalog): View
    {
        $this->case->load([
            'client:id,first_name,last_name,email,contact,policy_number,status,authorize_status,authorize_account_id',
            'paymentHistory:id,client_id,subscription_id,transaction_id,amount,currency,payment_date,payment_status,authorize_account_id,claim_id',
            'authorizeAccount:id,name,environment,is_active',
            'assignedUser:id,first_name,last_name,email,role_id',
        ]);

        return view('livewire.failed-payments.show', [
            'case' => $this->case,
            'attempts' => $this->case->attempts()->latest('id')->take(20)->get(),
            'retrySchedules' => $this->case->retrySchedules()->orderBy('sequence_number')->get(),
            'statusHistories' => $this->case->statusHistories()->latest('created_at')->take(20)->get(),
            'webhookEvents' => $this->case->webhookEvents()->latest('received_at')->take(20)->get(),
            'communications' => $this->case->communications()->latest('created_at')->take(20)->get(),
            'notes' => $this->case->notes()->latest('id')->get(),
            'auditLogs' => $this->case->auditLogs()->latest('id')->take(40)->get(),
            'statuses' => FailedPaymentCaseStatus::all(),
            'assignmentUsers' => User::query()->select(['id', 'first_name', 'last_name', 'email'])
                ->orderBy('first_name')->orderBy('last_name')->get(),
            'channels' => $catalog->channels(),
            'templatesByChannel' => $catalog->templateOptionsByChannel(auth()->id()),
            'canUpdate' => Gate::allows('update', $this->case),
            'canAssign' => Gate::allows('assign', $this->case),
            'canNotes' => Gate::allows('notes', $this->case),
            'canRetryControl' => Gate::allows('controlRetry', $this->case),
            'canSimulateRetry' => Gate::allows('simulateRetry', $this->case),
            'canCommunicate' => Gate::allows('communications', $this->case),
        ])->layout('layouts.app', ['title' => 'Failed Payment Case']);
    }
}
