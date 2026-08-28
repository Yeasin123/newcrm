<?php

namespace App\Services\Billing;

use App\Models\Client;
use Illuminate\Support\Carbon;

/**
 * Outbound "payment resolved / failed" notification. Bound to a concrete
 * implementation in AppServiceProvider from `config('billing.outbound_webhooks_enabled')`
 * — Null by default so dev / test never post to the real Zapier / ahc-* URLs.
 */
interface PaymentOutcomeNotifier
{
    public function notify(?Client $client, bool $success, Carbon $eventDate): void;
}
