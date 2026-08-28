<?php

namespace App\Listeners;

use App\Events\LeadCreated;
use App\Services\Lead\LeadAutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued reaction to a new lead: run the welcome-email automation. Bails
 * immediately (before touching the queue's retry budget) unless the feature is
 * on. LeadAutomationService keeps the send duplicate-safe and rethrows on a
 * transient failure so the queue — not a person — retries it.
 */
class SendLeadWelcomeEmail implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(private readonly LeadAutomationService $automation) {}

    public function handle(LeadCreated $event): void
    {
        if (! config('ai.welcome_email.enabled')) {
            return;
        }

        $this->automation->runWelcomeEmail($event->lead);
    }
}
