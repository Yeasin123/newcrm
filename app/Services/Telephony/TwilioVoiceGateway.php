<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Live voice call via the Twilio REST API (form POST + basic auth — no SDK,
 * matching this project's Authorize.Net approach). Ported from the legacy
 * FailedPaymentAdminWorkflowService::sendLiveVoice. Only bound when
 * config('telephony.voice_enabled').
 */
class TwilioVoiceGateway implements VoiceGateway
{
    public function call(string $to, string $message, ?string $audioUrl = null): array
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.phone_number');

        if ($sid === '' || $token === '' || $from === '') {
            return ['success' => false, 'error' => 'Twilio voice credentials are not configured.'];
        }

        $twiml = $audioUrl !== null && $audioUrl !== ''
            ? '<Response><Play>'.htmlspecialchars($audioUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Play></Response>'
            : '<Response><Say voice="alice">'.htmlspecialchars(Str::limit(strip_tags($message), 900, ''), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Say></Response>';

        $response = Http::asForm()->withBasicAuth($sid, $token)->acceptJson()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json", [
                'To' => $to,
                'From' => $from,
                'Twiml' => $twiml,
            ]);

        if ($response->successful()) {
            return ['success' => true, 'provider_reference' => data_get($response->json(), 'sid')];
        }

        return [
            'success' => false,
            'error' => data_get($response->json(), 'message') ?: $response->body(),
        ];
    }
}
