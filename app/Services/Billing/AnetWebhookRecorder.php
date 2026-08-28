<?php

namespace App\Services\Billing;

use App\Models\AnetWebhookEvent;
use App\Models\AuthorizeAccount;

/**
 * Idempotency + persistence for inbound Authorize.Net notify webhooks.
 * Ported from the legacy `webhookEventExists` / `recordWebhookEvent`.
 */
class AnetWebhookRecorder
{
    public function eventExists(?string $eventId): bool
    {
        if (! $eventId) {
            return false;
        }

        return AnetWebhookEvent::where('event_id', $eventId)->exists();
    }

    public function record(AuthorizeAccount $account, ?string $eventId, ?string $eventType, ?string $payloadId, string $raw): void
    {
        if (! $eventId) {
            return;
        }

        AnetWebhookEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'authorize_account_id' => $account->id,
                'event_type' => $eventType,
                'payload_id' => $payloadId ?: null,
                'raw' => $raw,
            ],
        );
    }
}
