<?php

namespace App\Services\FailedPayment;

use App\Models\FailedPaymentSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Backing store for the Payment Failed "Recovery control center" screen.
 *
 * The whole control-center configuration lives as one JSON blob under the
 * `failed_payment_settings` key `control_center`, so this never has to fight
 * FailedPaymentSettingsService' strict per-key normalisers or its feature
 * tests. The three values the retry cron actually reads
 * (`retry_days` / `retry_time` / `retry_timezone`) are mirrored back onto the
 * individual FailedPaymentSetting rows on every save so the automated
 * processor keeps honouring the schedule chosen here.
 *
 * Nothing in here can send: outbound comms + real charges stay gated by
 * config('comms.*') and the billing two-flag regardless of what an admin
 * toggles on this screen.
 */
class RecoveryControlCenterSettings
{
    public const KEY = 'control_center';

    public const MODES = ['test', 'live', 'paused'];

    public function defaults(): array
    {
        return [
            'operating_mode' => 'paused',        // test | live | paused
            'emergency_pause' => true,
            'hourly_attempt_limit' => 50,
            'daily_safety_stop' => 100,
            'smart_retries_enabled' => false,
            'board_collect_mode' => 'always',     // always | require_charge

            'charge_schedule' => [
                ['day' => 1, 'time' => '09:00'],
                ['day' => 3, 'time' => '12:00'],
                ['day' => 5, 'time' => '15:00'],
                ['day' => 7, 'time' => '10:00'],
                ['day' => 9, 'time' => '14:00'],
            ],
            'max_monthly_retry_windows' => 3,
            'monthly_overdue_cap' => 4,
            'charge_timezone' => 'America/New_York',

            'outreach' => [
                'enabled' => false,
                'send_time' => '09:00',
                'timezone' => 'America/New_York',
                'channels' => ['sms' => false, 'email' => false, 'voicemail' => false],
                'stop_after_reply' => false,
                'stop_after_payment' => true,
                'honor_optouts' => true,
            ],

            'smart_timing' => [
                'original_billing_day' => false,
                'pay_cycle' => false,
                'previous_payment_timing' => false,
                'signup_billing_time' => false,
                'admin_patterns' => ['Thursday 12:00', 'Monday 09:00', 'Friday 15:00'],
            ],

            'templates' => [
                'email_subject' => 'Action Required: Update Your Payment for Policy #{policyNumber}',
                'email_from_name' => 'American Home Coverage',
                'email_from_email' => 'no-reply@americanhomecoverage.com',
                'link_expiry_days' => 7,
                'include_link' => true,
                'sms' => 'Hi {firstName}, your payment for policy {policyNumber} did not go through. Please update your payment method so your coverage stays active: {updateLink}',
                'email_body' => 'We could not process your payment for policy {policyNumber}. Please update your payment method using the secure link below.',
                'voicemail' => 'Hello {firstName}, this is American Home Coverage calling about a payment update for policy {policyNumber}. Please call us back or use the secure update link we sent you.',
            ],

            'destinations' => [
                'sms_url' => '',
                'email_url' => '',
                'voicemail_url' => '',
                'lifecycle_failed_url' => '',
                'lifecycle_recovered_url' => '',
                'lifecycle_resolved_url' => '',
            ],
        ];
    }

    public function load(): array
    {
        return array_replace_recursive($this->defaults(), $this->stored());
    }

    protected function stored(): array
    {
        try {
            if (! Schema::hasTable('failed_payment_settings')) {
                return [];
            }

            $row = FailedPaymentSetting::query()->where('setting_key', self::KEY)->first();
            $value = is_array($row?->setting_value) ? Arr::get($row->setting_value, 'value', []) : [];

            return is_array($value) ? $value : [];
        } catch (\Throwable $e) {
            Log::warning('Recovery control-center settings lookup skipped.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function save(array $input, ?int $adminId = null): array
    {
        $values = $this->sanitise($input);

        FailedPaymentSetting::query()->updateOrCreate(
            ['setting_key' => self::KEY],
            [
                'setting_value' => ['value' => $values],
                'is_active' => $values['operating_mode'] === 'live',
                'updated_by' => $adminId ? (string) $adminId : null,
            ],
        );

        $this->mirrorCronKeys($values, $adminId);

        return $values;
    }

    /** Whether Live Mode is actually reachable (billing two-flag is live). */
    public function liveModeAvailable(): bool
    {
        return config('billing.driver') === 'live'
            && config('billing.processing_mode') === 'live';
    }

    protected function sanitise(array $input): array
    {
        $d = $this->defaults();

        $mode = (string) Arr::get($input, 'operating_mode', $d['operating_mode']);
        $mode = in_array($mode, self::MODES, true) ? $mode : 'paused';
        if ($mode === 'live' && ! $this->liveModeAvailable()) {
            $mode = 'paused';
        }

        $collect = (string) Arr::get($input, 'board_collect_mode', $d['board_collect_mode']);

        return [
            'operating_mode' => $mode,
            'emergency_pause' => (bool) Arr::get($input, 'emergency_pause', $d['emergency_pause']),
            'hourly_attempt_limit' => $this->clampInt(Arr::get($input, 'hourly_attempt_limit'), 1, 10000, $d['hourly_attempt_limit']),
            'daily_safety_stop' => $this->clampInt(Arr::get($input, 'daily_safety_stop'), 1, 100000, $d['daily_safety_stop']),
            'smart_retries_enabled' => (bool) Arr::get($input, 'smart_retries_enabled', $d['smart_retries_enabled']),
            'board_collect_mode' => in_array($collect, ['always', 'require_charge'], true) ? $collect : 'always',

            'charge_schedule' => $this->sanitiseSchedule(Arr::get($input, 'charge_schedule', $d['charge_schedule'])),
            'max_monthly_retry_windows' => $this->clampInt(Arr::get($input, 'max_monthly_retry_windows'), 0, 24, $d['max_monthly_retry_windows']),
            'monthly_overdue_cap' => $this->clampInt(Arr::get($input, 'monthly_overdue_cap'), 1, 12, $d['monthly_overdue_cap']),
            'charge_timezone' => $this->tz(Arr::get($input, 'charge_timezone'), $d['charge_timezone']),

            'outreach' => [
                'enabled' => (bool) Arr::get($input, 'outreach.enabled', false),
                'send_time' => $this->time(Arr::get($input, 'outreach.send_time'), $d['outreach']['send_time']),
                'timezone' => $this->tz(Arr::get($input, 'outreach.timezone'), $d['outreach']['timezone']),
                'channels' => [
                    'sms' => (bool) Arr::get($input, 'outreach.channels.sms', false),
                    'email' => (bool) Arr::get($input, 'outreach.channels.email', false),
                    'voicemail' => (bool) Arr::get($input, 'outreach.channels.voicemail', false),
                ],
                'stop_after_reply' => (bool) Arr::get($input, 'outreach.stop_after_reply', false),
                'stop_after_payment' => (bool) Arr::get($input, 'outreach.stop_after_payment', true),
                'honor_optouts' => (bool) Arr::get($input, 'outreach.honor_optouts', true),
            ],

            'smart_timing' => [
                'original_billing_day' => (bool) Arr::get($input, 'smart_timing.original_billing_day', false),
                'pay_cycle' => (bool) Arr::get($input, 'smart_timing.pay_cycle', false),
                'previous_payment_timing' => (bool) Arr::get($input, 'smart_timing.previous_payment_timing', false),
                'signup_billing_time' => (bool) Arr::get($input, 'smart_timing.signup_billing_time', false),
                'admin_patterns' => $this->sanitisePatterns(Arr::get($input, 'smart_timing.admin_patterns', $d['smart_timing']['admin_patterns'])),
            ],

            'templates' => [
                'email_subject' => $this->str(Arr::get($input, 'templates.email_subject'), $d['templates']['email_subject'], 255),
                'email_from_name' => $this->str(Arr::get($input, 'templates.email_from_name'), $d['templates']['email_from_name'], 120),
                'email_from_email' => $this->str(Arr::get($input, 'templates.email_from_email'), $d['templates']['email_from_email'], 190),
                'link_expiry_days' => $this->clampInt(Arr::get($input, 'templates.link_expiry_days'), 1, 90, $d['templates']['link_expiry_days']),
                'include_link' => (bool) Arr::get($input, 'templates.include_link', true),
                'sms' => $this->str(Arr::get($input, 'templates.sms'), $d['templates']['sms'], 1000),
                'email_body' => $this->str(Arr::get($input, 'templates.email_body'), $d['templates']['email_body'], 4000),
                'voicemail' => $this->str(Arr::get($input, 'templates.voicemail'), $d['templates']['voicemail'], 2000),
            ],

            'destinations' => [
                'sms_url' => $this->url(Arr::get($input, 'destinations.sms_url')),
                'email_url' => $this->url(Arr::get($input, 'destinations.email_url')),
                'voicemail_url' => $this->url(Arr::get($input, 'destinations.voicemail_url')),
                'lifecycle_failed_url' => $this->url(Arr::get($input, 'destinations.lifecycle_failed_url')),
                'lifecycle_recovered_url' => $this->url(Arr::get($input, 'destinations.lifecycle_recovered_url')),
                'lifecycle_resolved_url' => $this->url(Arr::get($input, 'destinations.lifecycle_resolved_url')),
            ],
        ];
    }

    protected function sanitiseSchedule(mixed $rows): array
    {
        $rows = is_array($rows) ? $rows : [];
        $out = [];

        foreach ($rows as $row) {
            $day = $this->clampInt(Arr::get((array) $row, 'day'), 1, 365, 0);
            if ($day === 0) {
                continue;
            }
            $out[] = ['day' => $day, 'time' => $this->time(Arr::get((array) $row, 'time'), '12:00')];
        }

        usort($out, fn ($a, $b) => $a['day'] <=> $b['day']);

        return $out !== [] ? array_slice($out, 0, 10) : $this->defaults()['charge_schedule'];
    }

    protected function sanitisePatterns(mixed $patterns): array
    {
        $patterns = is_array($patterns) ? $patterns : [];
        $out = [];

        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern !== '' && ! in_array($pattern, $out, true)) {
                $out[] = mb_substr($pattern, 0, 60);
            }
        }

        return array_slice($out, 0, 12);
    }

    protected function mirrorCronKeys(array $values, ?int $adminId): void
    {
        $days = array_values(array_unique(array_map(fn ($r) => (int) $r['day'], $values['charge_schedule'])));
        sort($days);
        $time = $values['charge_schedule'][0]['time'] ?? '12:00';
        $tz = $values['charge_timezone'];

        $mirror = [
            'retry_days' => $days,
            'retry_time' => $time,
            'retry_timezone' => $tz,
        ];

        try {
            foreach ($mirror as $key => $value) {
                FailedPaymentSetting::query()->updateOrCreate(
                    ['setting_key' => $key],
                    [
                        'setting_value' => ['value' => $value],
                        'is_active' => true,
                        'updated_by' => $adminId ? (string) $adminId : null,
                    ],
                );
                Config::set('failed_payments.'.$key, $value);
            }

            // Test / Paused always keep the automated processor in dry-run.
            if ($values['operating_mode'] !== 'live') {
                Config::set('failed_payments.dry_run_mode', true);
            }
        } catch (\Throwable $e) {
            Log::warning('Recovery control-center cron-key mirror skipped.', ['error' => $e->getMessage()]);
        }
    }

    protected function clampInt(mixed $value, int $min, int $max, int $default): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    protected function time(mixed $value, string $default): string
    {
        $value = trim((string) $value);

        if (preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $value)) {
            [$h, $m] = explode(':', $value);

            return str_pad($h, 2, '0', STR_PAD_LEFT).':'.$m;
        }

        return $default;
    }

    protected function tz(mixed $value, string $default): string
    {
        $value = trim((string) $value);

        return in_array($value, timezone_identifiers_list(), true) ? $value : $default;
    }

    protected function str(mixed $value, string $default, int $max): string
    {
        $value = trim((string) $value);

        return $value === '' ? $default : mb_substr($value, 0, $max);
    }

    protected function url(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? mb_substr($value, 0, 500) : '';
    }
}
