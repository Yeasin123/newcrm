<?php

namespace App\Services\Billing;

use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live implementation — posts a compact payload to the configured
 * resolved / failed webhook URLs. Mirrors the legacy
 * `ClaimIntakeWebhook::sendPayment{Resolved,Failed}` fan-out but as a single
 * best-effort POST per outcome (failures are swallowed + logged, exactly as
 * the old `notifyPaymentOutcome` did).
 */
class ZapierPaymentOutcomeNotifier implements PaymentOutcomeNotifier
{
    public function notify(?Client $client, bool $success, Carbon $eventDate): void
    {
        if (! $client) {
            return;
        }

        $payload = [
            'client_id' => $client->id,
            'policy_number' => $client->policy_number,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'contact' => $client->contact,
            'event_date' => $eventDate->format('m-d-Y'),
            'outcome' => $success ? 'resolved' : 'failed',
        ];

        $urls = array_filter([
            config('billing.outbound.'.($success ? 'payment_resolved_url' : 'payment_failed_url')),
            config('billing.outbound.'.($success ? 'payment_resolved_zapier_url' : 'payment_failed_zapier_url')),
        ]);

        foreach ($urls as $url) {
            try {
                Http::timeout(8)->asJson()->post($url, $payload);
            } catch (\Throwable $e) {
                Log::error('Payment outcome webhook notification failed', [
                    'client_id' => $client->id,
                    'success' => $success,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
