<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Live ringless voicemail via JustDeliverit RVM. Ported from the legacy
 * FailedPaymentAdminWorkflowService::sendLiveVoicemail (Guzzle → Laravel Http).
 * Only bound when config('telephony.voicemail_enabled').
 */
class JdiVoicemailGateway implements VoicemailGateway
{
    public function drop(string $to, string $audioUrl, ?string $campaignName = null): array
    {
        $apiUrl = $this->normalizeBaseUrl((string) config('services.jdi.api_base_url', 'https://api.23.rvmpro.com'));
        $token = (string) config('services.jdi.api_token');

        if ($token === '') {
            return ['success' => false, 'error' => 'Voicemail delivery token is not configured.'];
        }

        if ($audioUrl === '') {
            return ['success' => false, 'error' => 'A voicemail audio URL is required.'];
        }

        $callback = (string) preg_replace('/\D+/', '', (string) (config('services.jdi.callback_number') ?: config('services.twilio.phone_number', '')));

        // camelCase keys — the JSON body schema (snake_case is query-string only).
        $response = Http::acceptJson()
            ->withHeaders(['X-API-Token' => $token])
            ->post(rtrim($apiUrl, '/').'/api/campaigns', [
                'cName' => Str::limit($campaignName ?: 'RVM drop', 25, ''),
                'cCallback' => $callback,
                'cNumbers' => $to,
                'cWav' => $audioUrl,
                'cStart' => true,
            ]);

        if ($response->successful()) {
            return ['success' => true, 'provider_reference' => (string) data_get($response->json(), 'cId')];
        }

        return ['success' => false, 'error' => Str::limit($response->body(), 500, '')];
    }

    protected function normalizeBaseUrl(string $apiUrl): string
    {
        $apiUrl = trim($apiUrl);

        if ($apiUrl === '') {
            return 'https://api.23.rvmpro.com';
        }

        $apiUrl = preg_replace('#/api/campaigns/?$#i', '', $apiUrl) ?? $apiUrl;
        $apiUrl = preg_replace('#/campaigns/?$#i', '', $apiUrl) ?? $apiUrl;

        return rtrim($apiUrl, '/');
    }
}
