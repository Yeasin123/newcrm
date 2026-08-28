<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Charge driver
    |--------------------------------------------------------------------------
    | Two-flag gate before any real money can move (mirrors the FailedPayment
    | module design). Both must be flipped to 'live' for the checkout /
    | recovery layer (Phase 6c) to touch a gateway.
    */
    'driver' => env('BILLING_DRIVER', 'dry_run'),          // dry_run | live
    'processing_mode' => env('BILLING_PROCESSING_MODE', 'dry_run'), // dry_run | live

    /*
    |--------------------------------------------------------------------------
    | Inbound Authorize.Net notify webhooks
    |--------------------------------------------------------------------------
    | The receiver always verifies the x-anet-signature HMAC and always
    | returns HTTP 200. `sdk_reads_enabled` controls whether the create-notify
    | handler is allowed to call the gateway to fetch transaction details
    | (Phase 6c); when false the event is still recorded, just not expanded.
    */
    'anet' => [
        'sdk_reads_enabled' => env('BILLING_ANET_SDK_READS', false),
        'monthly_gap_days' => 35,   // legacy createMissingMonthlyPayments threshold
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound payment-outcome webhooks (Zapier / ahc-* endpoints)
    |--------------------------------------------------------------------------
    | Off by default so dev / test never hit the real endpoints. When enabled,
    | the ZapierPaymentOutcomeNotifier posts to these.
    */
    'outbound_webhooks_enabled' => env('BILLING_OUTBOUND_WEBHOOKS', false),
    'outbound' => [
        'payment_resolved_url' => env('PAYMENT_RESOLVED_WEBHOOK_URL'),
        'payment_failed_url' => env('PAYMENT_FAILED_WEBHOOK_URL'),
        'payment_resolved_zapier_url' => env('PAYMENT_RESOLVED_ZAPIER_WEBHOOK_URL'),
        'payment_failed_zapier_url' => env('PAYMENT_FAILED_ZAPIER_WEBHOOK_URL'),
    ],
];
