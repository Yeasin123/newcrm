<?php

namespace App\Services\FailedPayment;

use App\Models\FailedPaymentSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FailedPaymentSettingsService
{
    public const SETTING_KEYS = [
        'module_enabled',
        'capture_enabled',
        'admin_enabled',
        'dry_run_mode',
        'automatic_retry_enabled',
        'manual_retry_enabled',
        'communication_simulation_enabled',
        'email_enabled',
        'sms_enabled',
        'voice_enabled',
        'voicemail_enabled',
        'retryable_failure_categories',
        'retry_days',
        'retry_timezone',
        'retry_time',
        'daily_communication',
        'communication_rules',
    ];

    public function defaults(): array
    {
        return [
            'module_enabled' => true,
            'capture_enabled' => true,
            'admin_enabled' => true,
            'dry_run_mode' => true,
            'automatic_retry_enabled' => false,
            'manual_retry_enabled' => false,
            'communication_simulation_enabled' => true,
            'email_enabled' => false,
            'sms_enabled' => false,
            'voice_enabled' => false,
            'voicemail_enabled' => false,
            'retryable_failure_categories' => ['insufficient_funds', 'temporary_gateway_error', 'processor_unavailable', 'declined', 'suspended'],
            'retry_days' => [1, 3, 7, 14, 30],
            'retry_timezone' => 'UTC',
            'retry_time' => '12:00',
            'daily_communication' => [
                'enabled' => false,
                'times' => ['12:30', '17:00'],
                'channels' => [
                    'email' => ['enabled' => false, 'template' => ''],
                    'sms' => ['enabled' => false, 'template' => ''],
                    'voice' => ['enabled' => false, 'template' => ''],
                    'voicemail' => ['enabled' => false, 'template' => ''],
                ],
            ],
            'communication_rules' => [],
        ];
    }

    public function load(): array
    {
        return $this->currentRuntimeValues();
    }

    public function syncConfig(): array
    {
        try {
            $values = $this->storedValues();
        } catch (\Throwable $e) {
            Log::warning('Failed-payment settings sync fell back to defaults.', [
                'error' => $e->getMessage(),
            ]);
            $values = [];
        }

        foreach ($values as $key => $value) {
            Config::set('failed_payments.'.$key, $value);
        }

        return $this->currentRuntimeValues();
    }

    public function save(array $input, ?int $adminId = null): array
    {
        $values = $this->defaults();
        $values['module_enabled'] = (bool) Arr::get($input, 'module_enabled', false);
        $values['capture_enabled'] = (bool) Arr::get($input, 'capture_enabled', false);
        $values['admin_enabled'] = (bool) Arr::get($input, 'admin_enabled', false);
        $values['dry_run_mode'] = (bool) Arr::get($input, 'dry_run_mode', false);
        $values['automatic_retry_enabled'] = (bool) Arr::get($input, 'automatic_retry_enabled', false);
        $values['manual_retry_enabled'] = (bool) Arr::get($input, 'manual_retry_enabled', false);
        $values['communication_simulation_enabled'] = (bool) Arr::get($input, 'communication_simulation_enabled', false);
        $values['email_enabled'] = (bool) Arr::get($input, 'email_enabled', false);
        $values['sms_enabled'] = (bool) Arr::get($input, 'sms_enabled', false);
        $values['voice_enabled'] = (bool) Arr::get($input, 'voice_enabled', false);
        $values['voicemail_enabled'] = (bool) Arr::get($input, 'voicemail_enabled', false);
        $values['retryable_failure_categories'] = $this->normalizeRetryableCategories((string) Arr::get($input, 'retryable_failure_categories', 'insufficient_funds,temporary_gateway_error,processor_unavailable,declined,suspended'));
        $values['retry_timezone'] = $this->normalizeTimezone((string) Arr::get($input, 'retry_timezone', 'UTC'));
        $values['retry_time'] = $this->normalizeRetryTime((string) Arr::get($input, 'retry_time', '12:00'));
        $values['retry_days'] = $this->normalizeRetryDays((string) Arr::get($input, 'retry_days', '1,3,7,14,30'));
        $values['daily_communication'] = $this->normalizeDailyCommunication(Arr::get($input, 'daily_communication', []));
        $values['communication_rules'] = $this->normalizeCommunicationRules(Arr::get($input, 'communication_rules', []));

        foreach ($values as $key => $value) {
            FailedPaymentSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => ['value' => $value],
                    'is_active' => is_bool($value) ? $value : true,
                    'updated_by' => $adminId ? (string) $adminId : null,
                ]
            );
        }

        foreach ($values as $key => $value) {
            Config::set('failed_payments.'.$key, $value);
        }

        return $values;
    }

    public function formValues(): array
    {
        return $this->currentRuntimeValues();
    }

    protected function currentRuntimeValues(): array
    {
        $values = [];

        foreach ($this->defaults() as $key => $defaultValue) {
            $values[$key] = config('failed_payments.'.$key, $defaultValue);
        }

        return array_replace($values, $this->storedValues());
    }

    protected function storedValues(): array
    {
        try {
            $values = [];

            if (! Schema::hasTable('failed_payment_settings')) {
                return $values;
            }

            $settings = FailedPaymentSetting::query()
                ->whereIn('setting_key', self::SETTING_KEYS)
                ->get()
                ->keyBy('setting_key');

            foreach ($this->defaults() as $key => $defaultValue) {
                $row = $settings->get($key);
                if (! $row) {
                    continue;
                }

                $values[$key] = $this->normalizeForKey($key, $row->is_active, $row->setting_value, $defaultValue);
            }

            return $values;
        } catch (\Throwable $e) {
            Log::warning('Failed-payment settings store lookup skipped.', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function normalizeForKey(string $key, mixed $isActive, mixed $settingValue, mixed $default): mixed
    {
        $value = is_array($settingValue) ? Arr::get($settingValue, 'value', $default) : $default;

        return match ($key) {
            'module_enabled',
            'capture_enabled',
            'admin_enabled',
            'dry_run_mode',
            'automatic_retry_enabled',
            'manual_retry_enabled',
            'communication_simulation_enabled',
            'email_enabled',
            'sms_enabled',
            'voice_enabled',
            'voicemail_enabled' => (bool) ($isActive ?? $value ?? $default),
            'retryable_failure_categories' => $this->normalizeRetryableCategories(is_array($value) ? implode(',', $value) : (string) $value),
            'retry_days' => $this->normalizeRetryDays(is_array($value) ? implode(',', $value) : (string) $value),
            'retry_timezone' => $this->normalizeTimezone((string) $value),
            'retry_time' => $this->normalizeRetryTime((string) $value),
            'daily_communication' => $this->normalizeDailyCommunication($value),
            'communication_rules' => $this->normalizeCommunicationRules($value),
            default => $value,
        };
    }

    protected function normalizeRetryableCategories(string $value): array
    {
        $categories = array_values(array_filter(array_map(static function ($item) {
            $item = strtolower(trim((string) $item));
            $item = str_replace([' ', '-'], '_', $item);

            return $item === '' ? null : preg_replace('/[^a-z0-9_]/', '', $item);
        }, preg_split('/\s*,\s*/', $value) ?: []), static fn ($item) => $item !== null && $item !== ''));

        $categories = array_values(array_unique($categories));
        sort($categories);

        return $categories !== [] ? $categories : ['insufficient_funds', 'temporary_gateway_error', 'processor_unavailable', 'declined', 'suspended'];
    }

    protected function normalizeRetryDays(string $value): array
    {
        $values = array_values(array_filter(array_map(static function ($item) {
            $item = trim((string) $item);

            return $item === '' ? null : (int) $item;
        }, preg_split('/\s*,\s*/', $value) ?: []), static fn ($item) => $item !== null && $item > 0));

        $values = array_values(array_unique($values));
        sort($values);

        return $values !== [] ? $values : [1, 3, 7, 14, 30];
    }

    protected function normalizeTimezone(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? $value : 'UTC';
    }

    protected function normalizeRetryTime(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $value)) {
            return str_pad(explode(':', $value)[0], 2, '0', STR_PAD_LEFT).':'.explode(':', $value)[1];
        }

        return '12:00';
    }

    protected function normalizeCommunicationRules(mixed $value): array
    {
        $catalog = app(FailedPaymentCommunicationTemplateCatalog::class);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $day = (int) Arr::get($rule, 'day', 0);
            if ($day <= 0) {
                continue;
            }

            $times = $this->normalizeRetryTimes($rule);
            $channels = [];

            foreach ($catalog->channels() as $channel) {
                $enabled = filter_var(Arr::get($rule, 'channels.'.$channel.'.enabled', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                $enabled = $enabled ?? (bool) Arr::get($rule, 'channels.'.$channel.'.enabled', false);

                if (! $enabled) {
                    continue;
                }

                $template = strtolower(trim((string) Arr::get($rule, 'channels.'.$channel.'.template', $catalog->defaultTemplateIdForChannel($channel) ?? '')));
                $allowedTemplates = $catalog->allowedTemplateIdsForChannel($channel);

                if (! in_array($template, $allowedTemplates, true)) {
                    $template = $allowedTemplates[0] ?? ($catalog->defaultTemplateIdForChannel($channel) ?? '');
                }

                if ($template === '') {
                    continue;
                }

                $channels[$channel] = [
                    'enabled' => true,
                    'template' => $template,
                ];
            }

            if ($channels === [] || $times === []) {
                continue;
            }

            $normalized[] = [
                'day' => $day,
                'time' => $times[0] ?? '12:00',
                'times' => $times,
                'channels' => $channels,
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $comparison = ($left['day'] ?? 0) <=> ($right['day'] ?? 0);

            if ($comparison !== 0) {
                return $comparison;
            }

            return strcmp((string) ($left['time'] ?? '00:00'), (string) ($right['time'] ?? '00:00'));
        });

        return $normalized;
    }

    protected function normalizeDailyCommunication(mixed $value): array
    {
        $catalog = app(FailedPaymentCommunicationTemplateCatalog::class);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            $value = [];
        }

        $enabled = filter_var(Arr::get($value, 'enabled', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $enabled = $enabled ?? (bool) Arr::get($value, 'enabled', false);
        $times = $this->normalizeRetryTimes($value);
        $channels = [];

        foreach ($catalog->channels() as $channel) {
            $channelEnabled = filter_var(Arr::get($value, 'channels.'.$channel.'.enabled', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $channelEnabled = $channelEnabled ?? (bool) Arr::get($value, 'channels.'.$channel.'.enabled', false);

            if (! $channelEnabled) {
                continue;
            }

            $template = strtolower(trim((string) Arr::get($value, 'channels.'.$channel.'.template', $catalog->defaultTemplateIdForChannel($channel) ?? '')));
            $allowedTemplates = $catalog->allowedTemplateIdsForChannel($channel);

            if (! in_array($template, $allowedTemplates, true)) {
                $template = $allowedTemplates[0] ?? ($catalog->defaultTemplateIdForChannel($channel) ?? '');
            }

            if ($template === '') {
                continue;
            }

            $channels[$channel] = [
                'enabled' => true,
                'template' => $template,
            ];
        }

        if (! $enabled || $times === [] || $channels === []) {
            return [
                'enabled' => false,
                'times' => [],
                'channels' => [],
            ];
        }

        return [
            'enabled' => true,
            'times' => $times,
            'channels' => $channels,
        ];
    }

    protected function normalizeRetryTimes(array $rule): array
    {
        $times = Arr::get($rule, 'times', []);

        if (! is_array($times) || $times === []) {
            $fallback = $this->normalizeRetryTime((string) Arr::get($rule, 'time', '12:00'));

            return $fallback !== '' ? [$fallback] : [];
        }

        $times = array_values(array_filter(array_map(function ($value) {
            return $this->normalizeRetryTime((string) $value);
        }, $times), static fn ($value) => $value !== ''));

        $times = array_values(array_unique($times));
        sort($times);

        if ($times === []) {
            $fallback = $this->normalizeRetryTime((string) Arr::get($rule, 'time', '12:00'));

            return $fallback !== '' ? [$fallback] : [];
        }

        return array_slice($times, 0, 2);
    }
}
