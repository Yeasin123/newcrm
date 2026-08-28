<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One inbound SMS reply from an "entrance" campaign channel. The legacy
 * `entrances` table: every reply from any of the numbered entrance webhooks
 * (`/zero-entrance` … `/nineth-entrance`) plus `/mm-webhook`,
 * `/new/yair-webhook/link` and `/avchad-webhook` writes a row here, keyed to
 * the matched `lead_details` row by `lead_detail_id`. `last_webhook_used` is
 * the round-robin cursor for the outbound VanillaSoft push.
 */
class Entrance extends Model
{
    protected $table = 'entrances';

    protected $fillable = [
        'contact', 'first_name', 'last_name', 'lead_detail_id', 'message',
        'campaign_id', 'channel_id', 'workspace_id', 'email', 'status',
        'address', 'zip_code', 'state', 'city', 'mark_color', 'webhook_type',
        'message_id', 'webhook_name', 'last_webhook_used', 'account_key',
    ];

    public function leadDetail()
    {
        return $this->belongsTo(LeadDetail::class, 'lead_detail_id');
    }
}
