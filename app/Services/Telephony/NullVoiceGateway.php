<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Log;

/** Records the intent, calls nobody. The default. */
class NullVoiceGateway implements VoiceGateway
{
    public function call(string $to, string $message, ?string $audioUrl = null): array
    {
        Log::info('Voice call suppressed (telephony.voice_enabled is off).', ['to' => $to]);

        return ['success' => false, 'error' => 'Voice delivery is disabled.'];
    }
}
