<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Http;

/**
 * Live SMS via Telnyx v2. Ported verbatim from the legacy TelnyxMessageService.
 * Only bound when config('telephony.sms_enabled').
 */
class TelnyxSmsGateway extends AbstractSmsGateway
{
    public function send(string $to, string $body, ?string $from = null): array
    {
        $apiKey = config('services.telnyx.api_key');
        $from = $this->normalizePhone($from ?: config('services.telnyx.from_number'));
        $to = $this->normalizePhone($to);

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'TELNYX_API_KEY is not configured.'];
        }

        if (empty($from)) {
            return ['success' => false, 'error' => 'TELNYX_FROM_NUMBER is not configured.'];
        }

        if (empty($to)) {
            return ['success' => false, 'error' => 'The recipient phone number is missing or invalid.'];
        }

        $payload = ['from' => $from, 'to' => $to, 'text' => $body];

        $profileId = config('services.telnyx.messaging_profile_id');
        if (! empty($profileId)) {
            $payload['messaging_profile_id'] = $profileId;
        }

        $response = Http::withToken($apiKey)->acceptJson()->asJson()
            ->post('https://api.telnyx.com/v2/messages', $payload);

        $responseBody = $response->json();

        if ($response->successful()) {
            return [
                'success' => true,
                'payload' => $responseBody,
                'message_id' => data_get($responseBody, 'data.id'),
            ];
        }

        return [
            'success' => false,
            'payload' => $responseBody,
            'error' => data_get($responseBody, 'errors.0.detail')
                ?: data_get($responseBody, 'errors.0.title')
                ?: $response->body(),
        ];
    }
}
