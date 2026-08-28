<?php

namespace App\Livewire\Commissions;

use App\Models\User;
use App\Services\Commission\CommissionSettingsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Commission Settings — plan-type rates (fixed formulas), base salesman
 * rates + per-user overrides (reference only), the Spliffy bonus tiers, the
 * weekly Billing Spliffy and the after-hours multiplier. One JSON blob in
 * billing_settings via CommissionSettingsService.
 */
class Settings extends Component
{
    /** @var array<string,mixed> */
    public array $cfg = [];

    public function mount(CommissionSettingsService $settings): void
    {
        $this->cfg = $this->shape($settings->all());
    }

    /** Normalise into a flat, form-friendly shape (rates as whole %). */
    private function shape(array $c): array
    {
        return [
            'monthly_pct' => round(($c['plan_rates']['monthly'] ?? 0.90) * 100, 2),
            'yearly_full_pct' => round(($c['plan_rates']['yearly_full'] ?? 0.099) * 100, 2),
            'yearly_inst_pct' => round(($c['plan_rates']['yearly_inst'] ?? 0.08) * 100, 2),
            'base_rate' => $c['base_rate'] ?? 8,
            'overrides' => $c['overrides'] ?? [],
            'spiff_enabled' => (bool) ($c['spiff']['enabled'] ?? true),
            'spiff_tiers' => array_values($c['spiff']['tiers'] ?? []),
            'billing_enabled' => (bool) ($c['billing_spliffy']['enabled'] ?? true),
            'billing_amount' => $c['billing_spliffy']['amount'] ?? 200,
            'ah_enabled' => (bool) ($c['after_hours']['enabled'] ?? true),
            'ah_cutoff' => $c['after_hours']['cutoff'] ?? 19,
            'ah_multiplier' => $c['after_hours']['multiplier'] ?? 1.5,
        ];
    }

    public function addTier(): void
    {
        $last = end($this->cfg['spiff_tiers']) ?: ['cards' => 0, 'amount' => 0];
        $this->cfg['spiff_tiers'][] = ['cards' => (int) $last['cards'] + 3, 'amount' => (float) $last['amount'] + 65];
    }

    public function removeTier(int $i): void
    {
        unset($this->cfg['spiff_tiers'][$i]);
        $this->cfg['spiff_tiers'] = array_values($this->cfg['spiff_tiers']);
    }

    public function save(CommissionSettingsService $settings): void
    {
        $c = $this->cfg;

        $settings->save([
            'plan_rates' => [
                'monthly' => (float) $c['monthly_pct'] / 100,
                'yearly_full' => (float) $c['yearly_full_pct'] / 100,
                'yearly_inst' => (float) $c['yearly_inst_pct'] / 100,
            ],
            'base_rate' => (float) $c['base_rate'],
            'overrides' => collect($c['overrides'])->filter(fn ($v) => is_numeric($v) && $v !== '')->map(fn ($v) => (float) $v)->all(),
            'spiff' => [
                'enabled' => (bool) $c['spiff_enabled'],
                'tiers' => collect($c['spiff_tiers'])->map(fn ($t) => [
                    'cards' => max(1, (int) ($t['cards'] ?? 1)),
                    'amount' => max(0, (float) ($t['amount'] ?? 0)),
                ])->values()->all(),
            ],
            'billing_spliffy' => [
                'enabled' => (bool) $c['billing_enabled'],
                'amount' => (float) $c['billing_amount'],
            ],
            'after_hours' => [
                'enabled' => (bool) $c['ah_enabled'],
                'cutoff' => (int) $c['ah_cutoff'],
                'multiplier' => (float) $c['ah_multiplier'],
            ],
            'two_x_event' => $settings->get('two_x_event'),
        ]);

        Flux::toast(variant: 'success', text: __('Commission settings saved.'));
    }

    public function render(): View
    {
        return view('livewire.commissions.settings', [
            'salesmen' => User::where('type', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'status']),
        ])->layout('layouts.app', ['title' => 'Commission Settings']);
    }
}
