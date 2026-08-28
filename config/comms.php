<?php

/*
|--------------------------------------------------------------------------
| Outbound communications master switch
|--------------------------------------------------------------------------
| The whole app is still in development against real customer data, so
| every outbound channel is OFF by default and stays off until a human
| deliberately turns it on from Control Center (or .env). `enabled` is the
| master — when it is false NOTHING goes out regardless of the per-channel
| flags or an individual gateway's own config.
*/

return [
    'enabled' => (bool) env('COMMS_ENABLED', false),

    'email' => (bool) env('COMMS_EMAIL', false),
    'sms' => (bool) env('COMMS_SMS', false),
    'voice' => (bool) env('COMMS_VOICE', false),
    'voicemail' => (bool) env('COMMS_VOICEMAIL', false),

    // Where mail goes when email is suppressed: 'log' keeps a copy in
    // storage/logs, 'array' drops it silently.
    'suppressed_mailer' => env('COMMS_SUPPRESSED_MAILER', 'log'),
];
