<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ANet live subscription checks
    |--------------------------------------------------------------------------
    | When true, the ANet Recurring Health screen calls Authorize.Net to verify
    | each subscription's status against the local record. Off by default so the
    | report never fires outbound HTTP on load.
    */
    'anet_live_checks' => (bool) env('REPORTING_ANET_LIVE_CHECKS', false),

];
