<?php

namespace App\Services\Telephony;

/**
 * Outbound voice call. Bound to NullVoiceGateway unless
 * config('telephony.voice_enabled').
 */
interface VoiceGateway
{
    /**
     * @param  string|null  $audioUrl  play this instead of speaking $message
     * @return array{success:bool, provider_reference?:?string, error?:?string}
     */
    public function call(string $to, string $message, ?string $audioUrl = null): array;
}
