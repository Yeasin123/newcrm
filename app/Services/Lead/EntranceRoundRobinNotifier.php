<?php

namespace App\Services\Lead;

use App\Models\Entrance;
use App\Models\LeadDetail;

/**
 * Pushes an inbound entrance reply on to the outbound VanillaSoft
 * round-robin (4 post URLs, rotated via entrances.last_webhook_used).
 * Bound to the Null implementation unless config('entrances.outbound_enabled').
 */
interface EntranceRoundRobinNotifier
{
    public function push(Entrance $entrance, LeadDetail $lead): void;
}
