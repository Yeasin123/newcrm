<?php

namespace App\Services\Dialer;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live 3CX call. Ported from the legacy CallerForVanilasoftController::getToken:
 * authenticate with the public key, then GET
 * UpdateExtOutboundCallerID/{urlencoded json}. Only bound when
 * config('dialer.threecx_enabled').
 */
class HttpThreeCxGateway implements ThreeCxGateway
{
    public function updateOutboundCallerId(?string $extNumber, string $callerIdBase64): mixed
    {
        $baseUrl = (string) config('dialer.threecx.base_url');
        $publicKey = (string) config('dialer.threecx.public_key');

        try {
            $client = Http::baseUrl($baseUrl)->acceptJson();

            $auth = $client->post('Authenticate', ['publicKey' => $publicKey]);

            if (! $auth->successful()) {
                return ['error' => 'Authentication failed', 'status' => $auth->status()];
            }

            $token = (string) data_get($auth->json(), 'token');
            $payload = json_encode(['extNumber' => $extNumber, 'newCallerId' => $callerIdBase64]);

            $update = $client->withToken($token)->acceptJson()
                ->get('UpdateExtOutboundCallerID/'.urlencode($payload));

            return $update->status() === 200
                ? $update->status()
                : ['error' => 'UpdateExtOutboundCallerID API request failed', 'status' => $update->status()];
        } catch (\Throwable $e) {
            Log::warning('3CX caller-ID update failed.', ['error' => $e->getMessage(), 'ext' => $extNumber]);

            return ['error' => $e->getMessage()];
        }
    }
}
