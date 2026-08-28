<?php

namespace App\Observers;

use App\Events\LeadCreated;
use App\Models\LeadDetail;
use App\Services\Analytics\AnalyticsSyncService;

/**
 * Fans out the `LeadCreated` event and, when the analytics mirror is enabled
 * (Phase 10c), keeps analytics_leads in sync.
 */
class LeadDetailObserver
{
    public function __construct(private readonly AnalyticsSyncService $analytics) {}

    public function created(LeadDetail $lead): void
    {
        LeadCreated::dispatch($lead);
        $this->analytics->syncLead($lead);
    }

    public function updated(LeadDetail $lead): void
    {
        $this->analytics->syncLead($lead);
    }

    public function deleted(LeadDetail $lead): void
    {
        $this->analytics->deleteLead($lead);
    }

    public function restored(LeadDetail $lead): void
    {
        $this->analytics->syncLead($lead);
    }
}
