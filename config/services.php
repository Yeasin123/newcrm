<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Telephony providers (Phase 9f). Credentials only — the on/off switches
    // live in config/telephony.php and default to disabled.
    'telnyx' => [
        'api_key' => env('TELNYX_API_KEY'),
        'from_number' => env('TELNYX_FROM_NUMBER'),
        'messaging_profile_id' => env('TELNYX_MESSAGING_PROFILE_ID'),
    ],

    'twilio' => [
        'account_sid' => env('ACCOUNT_SID'),
        'auth_token' => env('AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
    ],

    'jdi' => [
        'api_base_url' => env('JDI_API_BASE_URL', 'https://api.23.rvmpro.com'),
        'api_token' => env('JUSTDELIVERIT_API_TOKEN'),
        'callback_number' => env('JDI_CALLBACK_NUMBER'),
    ],

    // Zen Payments — chargeback / statement feed for Disputes + Financials.
    // Not wired yet: with no keys the Disputes "Zen" tab is a connect-prompt
    // and processor-statement sync is a no-op.
    'zen' => [
        'key_1' => env('ZEN_API_KEY_1'),
        'key_2' => env('ZEN_API_KEY_2'),
    ],

];
