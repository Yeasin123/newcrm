<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\Analytics\AnalyticsSyncService;

/** Keeps analytics_customers / analytics_sales_events in sync (Phase 10c). No-op unless the mirror is enabled. */
class ClientObserver
{
    public function __construct(private readonly AnalyticsSyncService $analytics) {}

    public function created(Client $client): void
    {
        $this->analytics->syncClient($client);
    }

    public function updated(Client $client): void
    {
        $this->analytics->syncClient($client);
    }

    public function deleted(Client $client): void
    {
        $this->analytics->deleteClient($client);
    }

    public function restored(Client $client): void
    {
        $this->analytics->syncClient($client);
    }
}
