<?php

namespace App\Services\Commission;

use App\Models\BillingSetting;
use Illuminate\Support\Arr;

/**
 * Commission configuration — plan-type rates, base salesman rates, Spliffy
 * bonus tiers, the weekly Billing Spliffy, the after-hours multiplier and a
 * one-off 2× window. Defaults come from config('commission.defaults');
 * overrides are one JSON blob in billing_settings['commission_config'].
 */
class CommissionSettingsService
{
    private const KEY = 'commission_config';

    /** @return array<string,mixed> merged defaults + stored overrides */
    public function all(): array
    {
        $stored = [];
        try {
            $raw = BillingSetting::where('key', self::KEY)->value('value');
            $stored = $raw ? (json_decode($raw, true) ?: []) : [];
        } catch (\Throwable) {
        }

        return $this->mergeDeep(config('commission.defaults', []), $stored);
    }

    public function get(string $dotKey, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $dotKey, $default);
    }

    /** @param array<string,mixed> $values full config array to persist */
    public function save(array $values): void
    {
        BillingSetting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => json_encode($this->sanitise($values))],
        );
    }

    /** Effective commission % for a salesman (base rate, then override). */
    public function rateFor(int $userId): float
    {
        $cfg = $this->all();
        $overrides = $cfg['overrides'] ?? [];

        return (float) ($overrides[$userId] ?? $overrides[(string) $userId] ?? $cfg['base_rate'] ?? 8.0);
    }

    /**
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $over
     * @return array<string,mixed>
     */
    private function mergeDeep(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            $base[$k] = is_array($v) && isset($base[$k]) && is_array($base[$k]) && ! array_is_list($v)
                ? $this->mergeDeep($base[$k], $v)
                : $v;
        }

        return $base;
    }

    /** @param array<string,mixed> $v */
    private function sanitise(array $v): array
    {
        $d = config('commission.defaults', []);

        return [
            'plan_rates' => [
                'monthly' => $this->clampRate($v['plan_rates']['monthly'] ?? $d['plan_rates']['monthly']),
                'yearly_full' => $this->clampRate($v['plan_rates']['yearly_full'] ?? $d['plan_rates']['yearly_full']),
                'yearly_inst' => $this->clampRate($v['plan_rates']['yearly_inst'] ?? $d['plan_rates']['yearly_inst']),
            ],
            'base_rate' => max(0, min(100, (float) ($v['base_rate'] ?? $d['base_rate']))),
            'overrides' => collect($v['overrides'] ?? [])
                ->filter(fn ($r) => is_numeric($r))
                ->map(fn ($r) => max(0, min(100, (float) $r)))
                ->all(),
            'spiff' => [
                'enabled' => (bool) ($v['spiff']['enabled'] ?? true),
                'tiers' => collect($v['spiff']['tiers'] ?? $d['spiff']['tiers'])
                    ->map(fn ($t) => ['cards' => max(1, (int) ($t['cards'] ?? 1)), 'amount' => max(0, (float) ($t['amount'] ?? 0))])
                    ->values()->all(),
            ],
            'billing_spliffy' => [
                'enabled' => (bool) ($v['billing_spliffy']['enabled'] ?? true),
                'amount' => max(0, (float) ($v['billing_spliffy']['amount'] ?? $d['billing_spliffy']['amount'])),
            ],
            'after_hours' => [
                'enabled' => (bool) ($v['after_hours']['enabled'] ?? true),
                'cutoff' => max(0, min(23, (int) ($v['after_hours']['cutoff'] ?? $d['after_hours']['cutoff']))),
                'multiplier' => max(1, (float) ($v['after_hours']['multiplier'] ?? $d['after_hours']['multiplier'])),
            ],
            'two_x_event' => $this->sanitiseEvent($v['two_x_event'] ?? null),
        ];
    }

    private function clampRate(mixed $r): float
    {
        $r = (float) $r;
        // Accept "90" as 0.90.
        if ($r > 1) {
            $r /= 100;
        }

        return max(0, min(1, $r));
    }

    private function sanitiseEvent(mixed $e): ?array
    {
        if (! is_array($e) || empty($e['date'])) {
            return null;
        }

        return [
            'date' => (string) $e['date'],
            'start' => (string) ($e['start'] ?? '18:00'),
            'end' => (string) ($e['end'] ?? '19:00'),
        ];
    }
}
