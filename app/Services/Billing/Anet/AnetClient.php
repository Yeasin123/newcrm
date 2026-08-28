<?php

namespace App\Services\Billing\Anet;

use App\Exceptions\AnetApiException;
use App\Models\AuthorizeAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level Authorize.Net JSON API client.
 *
 * Replaces the abandoned `authorizenet/authorizenet` v2 SDK (not PHP 8.3
 * compatible). Authorize.Net exposes one JSON endpoint that takes
 * `{"<requestName>": {...}}` and answers with JSON (prefixed by a UTF-8 BOM
 * that must be stripped before decoding).
 */
class AnetClient
{
    private const PROD = 'https://api.authorize.net/xml/v1/request.api';

    private const SANDBOX = 'https://apitest.authorize.net/xml/v1/request.api';

    /**
     * Send a request and return the decoded response body. Throws
     * AnetApiException unless `messages.resultCode === 'Ok'`.
     *
     * @param  array<string, mixed>  $body  the request object WITHOUT
     *                                      merchantAuthentication (added here)
     * @return array<string, mixed>
     */
    public function call(AuthorizeAccount $account, string $requestName, array $body): array
    {
        $endpoint = $account->environment === 'production' ? self::PROD : self::SANDBOX;

        $payload = [
            $requestName => array_merge([
                'merchantAuthentication' => [
                    'name' => (string) $account->api_login_id,
                    'transactionKey' => (string) $account->transaction_key,
                ],
            ], $body),
        ];

        $response = Http::timeout(30)
            ->acceptJson()
            ->withBody(json_encode($payload, JSON_UNESCAPED_SLASHES), 'application/json')
            ->post($endpoint);

        $decoded = $this->decode($response->body());

        $resultCode = data_get($decoded, 'messages.resultCode');

        if ($resultCode !== 'Ok') {
            $message = data_get($decoded, 'messages.message.0', []);
            $code = (string) data_get($message, 'code', 'UNKNOWN');
            $text = (string) data_get($message, 'text', 'Authorize.Net request failed.');

            // A transaction-level error is nested one deeper.
            $txnErr = data_get($decoded, 'transactionResponse.errors.0');
            if ($txnErr) {
                $code = (string) data_get($txnErr, 'errorCode', $code);
                $text = (string) data_get($txnErr, 'errorText', $text);
            }

            Log::warning('ANET API non-Ok result', [
                'authorize_account_id' => $account->id,
                'request' => $requestName,
                'code' => $code,
                'text' => $text,
            ]);

            throw new AnetApiException($code, $text, is_array($decoded) ? $decoded : []);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** Strip the BOM Authorize.Net prepends, then json_decode. */
    private function decode(string $raw): mixed
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = trim($raw);

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AnetApiException('E_JSON', 'Unparseable Authorize.Net response: '.json_last_error_msg());
        }

        return $decoded;
    }
}
