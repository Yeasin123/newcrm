<?php

/*
|--------------------------------------------------------------------------
| Vanilasoft / 3CX dialer integration
|--------------------------------------------------------------------------
| The inbound webhooks always record locally (caller-ID rotation, disposition
| tracking). Outbound calls to 3CX and Zapier only fire when explicitly
| enabled, so dev / tests never touch a live PBX.
*/

return [

    // Push the rotated caller ID to the 3CX PBX (Authenticate + UpdateExtOutboundCallerID).
    'threecx_enabled' => (bool) env('DIALER_3CX_ENABLED', false),
    'threecx' => [
        'base_url' => env('DIALER_3CX_BASE_URL', 'https://americanhomecoverage.my3cx.us:8801/api/'),
        'public_key' => env('DIALER_3CX_PUBLIC_KEY', ''),
    ],

    // Post Sale / Doobie dispositions on to Zapier.
    'zapier_enabled' => (bool) env('DIALER_ZAPIER_ENABLED', false),
    'zapier_sale_url' => env('DIALER_ZAPIER_SALE_URL', ''),

    // Fallback caller-ID state when nothing else matches (legacy default).
    'fallback_state' => env('DIALER_FALLBACK_STATE', 'NV'),
];
