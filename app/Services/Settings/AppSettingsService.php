<?php

namespace App\Services\Settings;

use App\Models\BillingSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime control centre. Defaults come from config; overrides live in the
 * `billing_settings` key/value table; apply() pushes the merged values back
 * into config('...') on every request (from AppServiceProvider::boot) so
 * bindings that read config — the PaymentGateway, the telephony gateways,
 * the mail default — see the admin's choices.
 *
 * Everything money- or comms-related defaults to the safe state. The
 * billing two-flag gate is unchanged (both `billing.driver` and
 * `billing.processing_mode` must be 'live' for a real charge).
 */
class AppSettingsService
{
    /** @var array<string, array{config: string, type: string, group: string}> */
    public const KEYS = [
        // Billing
        'billing_driver_live' => ['config' => 'billing.driver', 'type' => 'live_flag', 'group' => 'billing'],
        'billing_processing_live' => ['config' => 'billing.processing_mode', 'type' => 'live_flag', 'group' => 'billing'],
        'billing_anet_sdk_reads' => ['config' => 'billing.anet.sdk_reads_enabled', 'type' => 'bool', 'group' => 'billing'],
        'billing_outbound_webhooks' => ['config' => 'billing.outbound_webhooks_enabled', 'type' => 'bool', 'group' => 'billing'],

        // Communications
        'comms_enabled' => ['config' => 'comms.enabled', 'type' => 'bool', 'group' => 'comms'],
        'comms_email' => ['config' => 'comms.email', 'type' => 'bool', 'group' => 'comms'],
        'comms_sms' => ['config' => 'comms.sms', 'type' => 'bool', 'group' => 'comms'],
        'comms_voice' => ['config' => 'comms.voice', 'type' => 'bool', 'group' => 'comms'],
        'comms_voicemail' => ['config' => 'comms.voicemail', 'type' => 'bool', 'group' => 'comms'],

        // Integrations
        'ai_enabled' => ['config' => 'ai.enabled', 'type' => 'bool', 'group' => 'integrations'],
        'ai_welcome_email' => ['config' => 'ai.welcome_email.enabled', 'type' => 'bool', 'group' => 'integrations'],
        'analytics_mirror' => ['config' => 'analytics.mirror_enabled', 'type' => 'bool', 'group' => 'integrations'],
        'telephony_sms' => ['config' => 'telephony.sms_enabled', 'type' => 'bool', 'group' => 'integrations'],
        'telephony_voice' => ['config' => 'telephony.voice_enabled', 'type' => 'bool', 'group' => 'integrations'],
        'telephony_voicemail' => ['config' => 'telephony.voicemail_enabled', 'type' => 'bool', 'group' => 'integrations'],
    ];

    /** @return array<string, bool> setting key => effective boolean value */
    public function all(): array
    {
        $stored = $this->stored();
        $out = [];

        foreach (self::KEYS as $key => $meta) {
            $out[$key] = array_key_exists($key, $stored)
                ? filter_var($stored[$key], FILTER_VALIDATE_BOOL)
                : $this->configBool($meta);
        }

        return $out;
    }

    public function get(string $key): bool
    {
        return $this->all()[$key] ?? false;
    }

    /**
     * @param  array<string, bool>  $values  setting key => on/off
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! isset(self::KEYS[$key])) {
                continue;
            }

            BillingSetting::updateOrCreate(['key' => $key], ['value' => $value ? '1' : '0']);
        }

        $this->apply();
    }

    /** Merge the effective settings into live config so bindings see them. */
    public function apply(): void
    {
        if (! $this->tableExists()) {
            return;
        }

        foreach ($this->all() as $key => $on) {
            $meta = self::KEYS[$key];
            $value = $meta['type'] === 'live_flag' ? ($on ? 'live' : 'dry_run') : $on;
            config([$meta['config'] => $value]);
        }
    }

    public function billingIsLive(): bool
    {
        $all = $this->all();

        return $all['billing_driver_live'] && $all['billing_processing_live'];
    }

    /** @return array<string, string> stored key => raw value */
    private function stored(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return BillingSetting::query()->pluck('value', 'key')->all();
    }

    private function configBool(array $meta): bool
    {
        $raw = config($meta['config']);

        return $meta['type'] === 'live_flag' ? $raw === 'live' : filter_var($raw, FILTER_VALIDATE_BOOL);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('billing_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
