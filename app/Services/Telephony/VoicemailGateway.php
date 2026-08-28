<?php

namespace App\Services\Telephony;

/**
 * Ringless voicemail drop. Bound to NullVoicemailGateway unless
 * config('telephony.voicemail_enabled').
 */
interface VoicemailGateway
{
    /**
     * @return array{success:bool, provider_reference?:?string, error?:?string}
     */
    public function drop(string $to, string $audioUrl, ?string $campaignName = null): array;
}
