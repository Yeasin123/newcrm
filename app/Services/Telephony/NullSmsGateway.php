<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Log;

/** Records the intent, sends nothing. The default. */
class NullSmsGateway extends AbstractSmsGateway
{
    public function send(string $to, string $body, ?string $from = null): array
    {
        Log::info('SMS suppressed (telephony.sms_enabled is off).', [
            'to' => $this->normalizePhone($to),
        ]);

        return ['success' => false, 'error' => 'SMS delivery is disabled.'];
    }
}
