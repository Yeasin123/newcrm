<?php

namespace App\Services\Lead;

use App\Models\Entrance;
use App\Models\LeadDetail;

/** No-op — the default. Inbound handling still runs; nothing leaves the box. */
class NullEntranceRoundRobinNotifier implements EntranceRoundRobinNotifier
{
    public function push(Entrance $entrance, LeadDetail $lead): void
    {
        // intentionally does nothing
    }
}
