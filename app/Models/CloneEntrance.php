<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mirror of `entrances` used by the entrance-blast "clone" fire flow. Same
 * shape as App\Models\Entrance; legacy `clone_entrances`.
 */
class CloneEntrance extends Model
{
    protected $table = 'clone_entrances';

    protected $fillable = [
        'contact', 'first_name', 'last_name', 'lead_detail_id', 'message',
        'campaign_id', 'channel_id', 'workspace_id', 'email', 'status',
        'address', 'zip_code', 'state', 'city', 'mark_color', 'webhook_type',
        'message_id', 'webhook_name', 'last_webhook_used', 'account_key',
    ];
}
