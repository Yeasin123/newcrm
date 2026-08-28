<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Log;

/** Records the intent, drops nothing. The default. */
class NullVoicemailGateway implements VoicemailGateway
{
    public function drop(string $to, string $audioUrl, ?string $campaignName = null): array
    {
        Log::info('Voicemail suppressed (telephony.voicemail_enabled is off).', ['to' => $to]);

        return ['success' => false, 'error' => 'Voicemail delivery is disabled.'];
    }
}
