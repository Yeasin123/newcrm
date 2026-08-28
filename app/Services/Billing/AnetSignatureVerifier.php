<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the `x-anet-signature: sha512=<hex>` header on an inbound
 * Authorize.Net webhook against every merchant account's signature key, and
 * returns the account that matched. Ported verbatim from the legacy
 * `TransactionCheckController::detectAccountFromWebhook` (case-safe hex,
 * decrypt-safe, constant-time compare).
 */
class AnetSignatureVerifier
{
    public function detectAccount(string $rawBody, ?string $receivedSignature): ?AuthorizeAccount
    {
        $receivedSignature = trim((string) $receivedSignature);

        if ($receivedSignature === '' || stripos($receivedSignature, 'sha512=') !== 0) {
            return null;
        }

        $receivedHex = strtolower(str_replace('sha512=', '', $receivedSignature));

        foreach (AuthorizeAccount::query()->get() as $acc) {
            try {
                $sigKey = trim((string) $acc->signature_key);
            } catch (\Throwable $e) {
                Log::warning('ANET signature_key decrypt failed', [
                    'authorize_account_id' => $acc->id,
                    'err' => $e->getMessage(),
                ]);

                continue;
            }

            if ($sigKey === '') {
                continue;
            }

            $myHex = strtolower(hash_hmac('sha512', $rawBody, $sigKey));

            if (hash_equals($receivedHex, $myHex)) {
                return $acc;
            }
        }

        return null;
    }
}
