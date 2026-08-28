<?php

namespace App\Listeners;

use App\Events\LeadCreated;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder listener so the LeadCreated pipeline is wired end-to-end.
 * Replaced/joined by the AI welcome-email agent and automation log in Phase 11.
 */
class LogLeadCreated
{
    public function handle(LeadCreated $event): void
    {
        Log::info('Lead created', [
            'lead_id' => $event->lead->id,
            'campaign_id' => $event->lead->campaign_id,
            'email' => $event->lead->email,
        ]);
    }
}
