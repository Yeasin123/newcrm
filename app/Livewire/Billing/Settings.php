<?php

namespace App\Livewire\Billing;

use App\Services\Settings\AppSettingsService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Control Centre — every runtime switch in one screen: billing dry-run /
 * live, the outbound-communications master + per-channel switches, and the
 * optional integrations. All DB-backed (`billing_settings`) and applied to
 * live config on every request.
 *
 * The billing two-flag gate is unchanged: a real charge needs BOTH
 * "Charge driver = live" and "Processing mode = live".
 */
class Settings extends Component
{
    /** @var array<string, bool> */
    public array $s = [];

    public function mount(AppSettingsService $settings): void
    {
        $this->s = $settings->all();
    }

    public function save(AppSettingsService $settings): void
    {
        $settings->save($this->s);

        $billingLive = ($this->s['billing_driver_live'] ?? false) && ($this->s['billing_processing_live'] ?? false);
        $commsOn = $this->s['comms_enabled'] ?? false;

        Flux::toast(
            variant: $billingLive || $commsOn ? 'warning' : 'success',
            text: match (true) {
                $billingLive => __('Saved. Billing is now LIVE — real charges will be sent.'),
                $commsOn => __('Saved. Outbound communications are now ON.'),
                default => __('Settings saved.'),
            },
        );
    }

    public function render(): View
    {
        $groups = [
            'billing' => __('Billing'),
            'comms' => __('Outbound communications'),
            'integrations' => __('Integrations'),
        ];

        $rows = [];
        foreach (AppSettingsService::KEYS as $key => $meta) {
            $rows[$meta['group']][$key] = $key;
        }

        return view('livewire.billing.settings', [
            'groups' => $groups,
            'rows' => $rows,
            'labels' => $this->labels(),
            'billingLive' => ($this->s['billing_driver_live'] ?? false) && ($this->s['billing_processing_live'] ?? false),
            'commsOn' => $this->s['comms_enabled'] ?? false,
        ])->layout('layouts.app', ['title' => 'Control Center']);
    }

    /** @return array<string, array{0: string, 1: string}> key => [title, help] */
    private function labels(): array
    {
        return [
            'billing_driver_live' => [__('Charge driver = live'), __('config: billing.driver')],
            'billing_processing_live' => [__('Processing mode = live'), __('config: billing.processing_mode. Both this and the driver must be on for any real transaction.')],
            'billing_anet_sdk_reads' => [__('Expand create-notify webhooks'), __('Let the Authorize.Net notify handler fetch transaction details.')],
            'billing_outbound_webhooks' => [__('Send outbound payment webhooks'), __('Zapier / ahc-* endpoints. Keep OFF while testing.')],

            'comms_enabled' => [__('Communications master switch'), __('When OFF, no email / SMS / voice / voicemail can leave the app, whatever the switches below say.')],
            'comms_email' => [__('Email'), __('Route mail to a real mailer. Off => mail is logged, not sent.')],
            'comms_sms' => [__('SMS'), __('Telnyx. Also needs the SMS integration below.')],
            'comms_voice' => [__('Voice calls'), __('Twilio. Also needs the Voice integration below.')],
            'comms_voicemail' => [__('Ringless voicemail'), __('JustDeliverit. Also needs the Voicemail integration below.')],

            'ai_enabled' => [__('AI (Anthropic)'), __('config: ai.enabled — the lead welcome agent + automation.')],
            'ai_welcome_email' => [__('AI welcome email automation'), __('config: ai.welcome_email.enabled')],
            'analytics_mirror' => [__('Analytics mirror'), __('config: analytics.mirror_enabled — copies rows to the analytics DB.')],
            'telephony_sms' => [__('SMS transport (Telnyx)'), __('config: telephony.sms_enabled')],
            'telephony_voice' => [__('Voice transport (Twilio)'), __('config: telephony.voice_enabled')],
            'telephony_voicemail' => [__('Voicemail transport (JDI)'), __('config: telephony.voicemail_enabled')],
        ];
    }
}
