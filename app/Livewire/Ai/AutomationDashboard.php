<?php

namespace App\Livewire\Ai;

use App\Models\LeadAutomationLog;
use App\Models\LeadDetail;
use App\Services\Lead\LeadAutomationService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lead automation status. Phase 11's automation-status mini-dashboard: a
 * per-action status rollup and the recent lead_automation_logs rows, with a
 * one-click re-run for a failed entry.
 */
class AutomationDashboard extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $action = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('Settings'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'action'], true)) {
            $this->resetPage();
        }
    }

    public function retry(int $logId, LeadAutomationService $automation): void
    {
        abort_unless(auth()->user()?->hasPermission('Settings'), 403);

        $log = LeadAutomationLog::findOrFail($logId);
        $lead = LeadDetail::find($log->lead_id);

        if (! $lead) {
            Flux::toast(variant: 'danger', text: __('Lead no longer exists.'));

            return;
        }

        // Let the service re-run this action from a non-terminal state.
        $log->update(['status' => LeadAutomationLog::STATUS_PENDING, 'error_message' => null]);

        try {
            $result = match ($log->action_key) {
                LeadAutomationService::ACTION_WELCOME_EMAIL => $automation->runWelcomeEmail($lead),
                default => 'skipped',
            };
            Flux::toast(variant: 'success', text: __('Re-ran automation: :r', ['r' => $result]));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function render(): View
    {
        $rollup = LeadAutomationLog::query()
            ->selectRaw('action_key, status, COUNT(*) as total')
            ->groupBy('action_key', 'status')
            ->get()
            ->groupBy('action_key')
            ->map(fn ($rows) => $rows->pluck('total', 'status'));

        return view('livewire.ai.automation-dashboard', [
            'rollup' => $rollup,
            'welcomeEnabled' => (bool) config('ai.welcome_email.enabled'),
            'llmEnabled' => (bool) config('ai.enabled'),
            'logs' => LeadAutomationLog::query()
                ->with('lead:id,first_name,last_name,email')
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->when($this->action !== '', fn ($q) => $q->where('action_key', $this->action))
                ->latest('id')
                ->paginate(25),
        ])->layout('layouts.app', ['title' => 'Lead Automation']);
    }
}
