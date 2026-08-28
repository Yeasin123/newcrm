<?php

namespace App\Services\Billing;

use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/** Default: log only, never posts outbound. */
class NullPaymentOutcomeNotifier implements PaymentOutcomeNotifier
{
    public function notify(?Client $client, bool $success, Carbon $eventDate): void
    {
        if (! $client) {
            return;
        }

        Log::info('PaymentOutcomeNotifier (null): outbound webhook suppressed', [
            'client_id' => $client->id,
            'success' => $success,
            'event_date' => $eventDate->format('m-d-Y'),
        ]);
    }
}
