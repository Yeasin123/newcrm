<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `anet_webhook_event_reads` — per-admin read receipts on webhook events. */
class AnetWebhookEventRead extends Model
{
    protected $table = 'anet_webhook_event_reads';

    protected $fillable = ['anet_webhook_event_id', 'admin_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AnetWebhookEvent::class, 'anet_webhook_event_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
