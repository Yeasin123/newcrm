<?php

namespace App\Services\Settings;

use App\Models\BillingSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Small JSON key/value store for the Settings hub sections (branding, sale
 * animations, misc). Backed by the existing `billing_settings` table — no
 * new migration. Values are JSON-encoded; scalars round-trip fine too.
 */
class SettingsRepository
{
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $raw = BillingSetting::where('key', $this->k($key))->value('value');
        } catch (\Throwable) {
            return $default;
        }

        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    public function set(string $key, mixed $value): void
    {
        BillingSetting::updateOrCreate(
            ['key' => $this->k($key)],
            ['value' => is_string($value) ? $value : json_encode($value)],
        );

        Cache::forget('settings:'.$key);
    }

    /** @param array<string,mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            $this->set($k, $v);
        }
    }

    private function k(string $key): string
    {
        return 'settings_'.$key;
    }
}
