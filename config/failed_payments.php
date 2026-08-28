<?php

return [
    'module_enabled' => env('FAILED_PAYMENT_MODULE_ENABLED', false),
    'capture_enabled' => env('FAILED_PAYMENT_CAPTURE_ENABLED', false),
    'admin_enabled' => env('FAILED_PAYMENT_ADMIN_ENABLED', false),
    'automatic_retry_enabled' => env('FAILED_PAYMENT_AUTOMATIC_RETRY_ENABLED', false),
    'manual_retry_enabled' => env('FAILED_PAYMENT_MANUAL_RETRY_ENABLED', false),
    'email_enabled' => env('FAILED_PAYMENT_EMAIL_ENABLED', false),
    'sms_enabled' => env('FAILED_PAYMENT_SMS_ENABLED', false),
    'voice_enabled' => env('FAILED_PAYMENT_VOICE_ENABLED', false),
    'voicemail_enabled' => env('FAILED_PAYMENT_VOICEMAIL_ENABLED', false),
    'retry_gateway_driver' => env('FAILED_PAYMENT_RETRY_GATEWAY_DRIVER', 'dry_run'),
    'retry_processing_mode' => env('FAILED_PAYMENT_RETRY_PROCESSING_MODE', 'dry_run'),
];
