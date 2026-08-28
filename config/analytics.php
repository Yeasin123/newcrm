<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics mirror
    |--------------------------------------------------------------------------
    | When true, every Client / PaymentHistory / Claim / LeadDetail write is
    | mirrored (denormalised) into the `analytics` connection's analytics_*
    | tables. Off by default — the mirror is opt-in and is populated with
    | `php artisan analytics:backfill` once enabled.
    */
    'mirror_enabled' => (bool) env('ANALYTICS_MIRROR_ENABLED', false),

    'connection' => env('ANALYTICS_MIRROR_CONNECTION', 'analytics'),

];
