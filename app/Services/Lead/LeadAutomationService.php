<?php

namespace App\Services\Lead;

use App\Mail\NewLeadWelcomeMail;
use App\Models\LeadAutomationLog;
use App\Models\LeadDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The lead automation rule engine (Phase 11 — the "first genuinely new
 * feature"). Every action is duplicate-safe: one lead_automation_logs row per
 * (lead, action_key), created before the work and only ever advanced to a
 * terminal status. Rule checks run before the AI helper.
 */
class LeadAutomationService
{
    public const ACTION_WELCOME_EMAIL = 'welcome_email';

    public function __construct(private readonly LeadWelcomeAgentService $agent) {}

    /**
     * Send the welcome email for a lead, exactly once.
     *
     * @return string the resulting log status (sent | skipped | failed)
     */
    public function runWelcomeEmail(LeadDetail $lead): string
    {
        $log = LeadAutomationLog::firstOrCreate(
            ['lead_id' => $lead->id, 'action_key' => self::ACTION_WELCOME_EMAIL],
            ['status' => LeadAutomationLog::STATUS_PENDING],
        );

        // Already handled on a previous run.
        if (in_array($log->status, [LeadAutomationLog::STATUS_SENT, LeadAutomationLog::STATUS_SKIPPED], true)) {
            return $log->status;
        }

        if (($reason = $this->welcomeEmailSkipReason($lead)) !== null) {
            $log->update([
                'status' => LeadAutomationLog::STATUS_SKIPPED,
                'meta' => ['skip_reason' => $reason],
            ]);

            return LeadAutomationLog::STATUS_SKIPPED;
        }

        try {
            $copy = $this->agent->compose($lead);

            Mail::to($lead->email)->send(new NewLeadWelcomeMail($copy['subject'], $copy['body']));

            $log->update([
                'status' => LeadAutomationLog::STATUS_SENT,
                'sent_at' => Carbon::now(),
                'error_message' => null,
                'meta' => [
                    'is_ai_generated' => $copy['is_ai_generated'],
                    'reason' => $copy['reason'],
                    'subject' => $copy['subject'],
                ],
            ]);

            return LeadAutomationLog::STATUS_SENT;
        } catch (\Throwable $e) {
            Log::error('Lead welcome email failed.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);

            $log->update([
                'status' => LeadAutomationLog::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            // Rethrow so the queued listener retries via the queue, not a manual refresh.
            throw $e;
        }
    }

    /** @return string|null  the rule that blocks the send, or null if it may proceed */
    public function welcomeEmailSkipReason(LeadDetail $lead): ?string
    {
        if (! config('ai.welcome_email.enabled')) {
            return 'automation_disabled';
        }

        $email = trim((string) $lead->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'no_valid_email';
        }

        $allow = (array) config('ai.welcome_email.only_campaign_ids', []);
        if ($allow !== [] && ! in_array((int) $lead->campaign_id, $allow, true)) {
            return 'campaign_not_enabled';
        }

        if (config('ai.welcome_email.business_hours_only')) {
            [$start, $end] = config('ai.welcome_email.business_hours', [8, 20]);
            $hour = (int) Carbon::now()->format('G');
            if ($hour < $start || $hour >= $end) {
                return 'outside_business_hours';
            }
        }

        return null;
    }
}
