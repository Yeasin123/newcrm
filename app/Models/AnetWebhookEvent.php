<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Idempotency + audit store for inbound Authorize.Net notify webhooks.
 * Legacy `anet_webhook_events` (`event_id` unique).
 */
class AnetWebhookEvent extends Model
{
    protected $table = 'anet_webhook_events';

    protected $fillable = [
        'authorize_account_id', 'event_id', 'event_type', 'payload_id', 'raw',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AuthorizeAccount::class, 'authorize_account_id');
    }

    /** Legacy relation name used by the ANet report screens. */
    public function authorizeAccount(): BelongsTo
    {
        return $this->account();
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnetWebhookEventRead::class, 'anet_webhook_event_id');
    }

    /** Decoded raw body, best effort. */
    public function decodedPayload(): array
    {
        $decoded = json_decode((string) $this->raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
