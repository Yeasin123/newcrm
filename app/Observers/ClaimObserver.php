<?php

namespace App\Observers;

use App\Models\Claim;
use App\Services\Analytics\AnalyticsSyncService;

/** Keeps analytics_claims in sync (Phase 10c). No-op unless the mirror is enabled. */
class ClaimObserver
{
    public function __construct(private readonly AnalyticsSyncService $analytics) {}

    public function created(Claim $claim): void
    {
        $this->analytics->syncClaim($claim);
    }

    public function updated(Claim $claim): void
    {
        $this->analytics->syncClaim($claim);
    }

    public function deleted(Claim $claim): void
    {
        $this->analytics->deleteClaim($claim);
    }

    public function restored(Claim $claim): void
    {
        $this->analytics->syncClaim($claim);
    }
}
