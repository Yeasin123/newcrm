<?php

namespace App\Livewire\FailedPayments;

use App\Services\FailedPayment\FailedPaymentSettingsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Failed-payment module feature-flag console. Ported from the legacy
 * FailedPaymentSettingsController — the same keys, the same
 * FailedPaymentSettingsService::save()/syncConfig() round-trip. Every live
 * lever defaults OFF; nothing here moves money or sends a message on its own.
 */
class Settings extends Component
{
    public bool $module_enabled = true;

    public bool $capture_enabled = true;

    public bool $admin_enabled = true;

    public bool $dry_run_mode = true;

    public bool $automatic_retry_enabled = false;

    public bool $manual_retry_enabled = false;

    public bool $communication_simulation_enabled = true;

    public bool $email_enabled = false;

    public bool $sms_enabled = false;

    public bool $voice_enabled = false;

    public bool $voicemail_enabled = false;

    public string $retryable_failure_categories = '';

    public string $retry_days = '';

    public string $retry_timezone = 'UTC';

    public string $retry_time = '12:00';

    public function mount(FailedPaymentSettingsService $settings): void
    {
        abort_unless(auth()->user()?->hasPermission('failed-payments.settings'), 403);

        $values = $settings->formValues();

        foreach (['module_enabled', 'capture_enabled', 'admin_enabled', 'dry_run_mode', 'automatic_retry_enabled', 'manual_retry_enabled', 'communication_simulation_enabled', 'email_enabled', 'sms_enabled', 'voice_enabled', 'voicemail_enabled'] as $flag) {
            $this->{$flag} = (bool) ($values[$flag] ?? false);
        }

        $this->retryable_failure_categories = implode(', ', (array) ($values['retryable_failure_categories'] ?? []));
        $this->retry_days = implode(', ', (array) ($values['retry_days'] ?? []));
        $this->retry_timezone = (string) ($values['retry_timezone'] ?? 'UTC');
        $this->retry_time = (string) ($values['retry_time'] ?? '12:00');
    }

    public function save(FailedPaymentSettingsService $settings): void
    {
        abort_unless(auth()->user()?->hasPermission('failed-payments.settings'), 403);

        $this->validate([
            'retryable_failure_categories' => ['nullable', 'string', 'max:500'],
            'retry_days' => ['nullable', 'string', 'max:200'],
            'retry_timezone' => ['required', 'string', 'max:64'],
            'retry_time' => ['required', 'string', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
        ]);

        $values = $settings->save([
            'module_enabled' => $this->module_enabled,
            'capture_enabled' => $this->capture_enabled,
            'admin_enabled' => $this->admin_enabled,
            'dry_run_mode' => $this->dry_run_mode,
            'automatic_retry_enabled' => $this->automatic_retry_enabled,
            'manual_retry_enabled' => $this->manual_retry_enabled,
            'communication_simulation_enabled' => $this->communication_simulation_enabled,
            'email_enabled' => $this->email_enabled,
            'sms_enabled' => $this->sms_enabled,
            'voice_enabled' => $this->voice_enabled,
            'voicemail_enabled' => $this->voicemail_enabled,
            'retryable_failure_categories' => $this->retryable_failure_categories,
            'retry_days' => $this->retry_days,
            'retry_timezone' => $this->retry_timezone,
            'retry_time' => $this->retry_time,
        ], auth()->id());

        $settings->syncConfig();

        $this->retryable_failure_categories = implode(', ', (array) $values['retryable_failure_categories']);
        $this->retry_days = implode(', ', (array) $values['retry_days']);

        Flux::toast(variant: 'success', text: __('Failed-payment settings saved.'));

        $offLive = collect([
            'email_enabled' => 'Email', 'sms_enabled' => 'SMS',
            'voice_enabled' => 'Voice', 'voicemail_enabled' => 'Voicemail',
        ])->reject(fn ($label, $key) => (bool) ($values[$key] ?? false))->values()->all();

        if ($offLive !== []) {
            Flux::toast(variant: 'warning', text: __('Live channels still OFF: ').implode(', ', $offLive));
        }
    }

    public function render(): View
    {
        return view('livewire.failed-payments.settings', [
            'liveRetryArmed' => strtolower((string) config('failed_payments.retry_processing_mode')) === 'live'
                && strtolower((string) config('failed_payments.retry_gateway_driver')) === 'live_cim'
                && ! config('failed_payments.dry_run_mode'),
        ])->layout('layouts.app', ['title' => 'Failed Payment Settings']);
    }
}
