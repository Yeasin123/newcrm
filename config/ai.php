<?php

/*
|--------------------------------------------------------------------------
| AI / LLM layer (Phase 11)
|--------------------------------------------------------------------------
| The CRM's LLM features (lead-welcome email drafting, …) go through
| App\Services\Ai\LlmGateway. It is bound to NullLlmGateway — which never
| calls out and always signals "unavailable" so callers use their fixed
| fallback — unless `enabled` is true AND an api key is present.
*/

return [

    'enabled' => (bool) env('AI_ENABLED', false),

    // Only 'claude' is implemented. Kept as a knob so a second provider can be
    // added behind the same interface later.
    'provider' => env('AI_PROVIDER', 'claude'),

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        // Cheap, fast model for the short high-volume drafting tasks.
        'model' => env('AI_MODEL', 'claude-haiku-4-5'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
        'timeout' => (int) env('AI_TIMEOUT', 20),
    ],

    /*
    | Lead welcome-email automation. OFF by default — turning it on makes every
    | new lead trigger a queued welcome email (AI-drafted when the LLM gateway
    | is available, fixed fallback otherwise), logged once in
    | lead_automation_logs.
    */
    'welcome_email' => [
        'enabled' => (bool) env('AI_WELCOME_EMAIL_ENABLED', false),
        // null = every campaign; otherwise a comma-separated allow-list of
        // campaign_files.id values.
        'only_campaign_ids' => array_filter(array_map(
            'intval',
            explode(',', (string) env('AI_WELCOME_EMAIL_CAMPAIGN_IDS', '')),
        )),
        'business_hours_only' => (bool) env('AI_WELCOME_EMAIL_BUSINESS_HOURS_ONLY', false),
        'business_hours' => [8, 20], // inclusive start, exclusive end (local time)
    ],

];
