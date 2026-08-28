<?php

namespace App\Events;

use App\Models\LeadDetail;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once for every newly created lead, from `LeadDetailObserver::created`.
 * Every lead-creation path must go through Eloquent `create()` (or dispatch
 * this manually after an insert) so downstream automation runs exactly once.
 *
 * Listeners (grown in later phases): analytics mirror sync (Phase 10),
 * AI welcome-email agent + automation log (Phase 11).
 */
class LeadCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public LeadDetail $lead) {}
}
