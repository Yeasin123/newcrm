<?php

/*
|--------------------------------------------------------------------------
| Telephony transports (Phase 9f)
|--------------------------------------------------------------------------
| Master on/off switches for the outbound SMS / voice / voicemail gateways.
| Every one defaults OFF — the Null implementation is bound instead, so no
| provider is ever contacted in dev or tests. Credentials live in
| config/services.php ('telnyx', 'twilio', 'jdi').
*/

return [
    'sms_enabled' => (bool) env('TELEPHONY_SMS_ENABLED', false),
    'voice_enabled' => (bool) env('TELEPHONY_VOICE_ENABLED', false),
    'voicemail_enabled' => (bool) env('TELEPHONY_VOICEMAIL_ENABLED', false),
];
