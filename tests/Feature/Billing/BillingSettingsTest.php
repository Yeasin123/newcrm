<?php

use App\Livewire\Billing\Settings;
use App\Models\User;
use App\Services\Billing\DryRunPaymentGateway;
use App\Services\Billing\LivePaymentGateway;
use App\Services\Billing\PaymentGateway;
use App\Services\Settings\AppSettingsService;
use App\Services\Telephony\NullSmsGateway;
use App\Services\Telephony\SmsGateway;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->superAdmin()->create()));

test('the control center renders and is permission-gated', function () {
    $this->get(route('billing.settings'))->assertOk()->assertSee('Control Center');

    $this->actingAs(User::factory()->withRole(['Search'])->create());
    $this->get(route('billing.settings'))->assertForbidden();
});

test('with nothing stored, values fall through to config', function () {
    config([
        'billing.driver' => 'dry_run', 'billing.processing_mode' => 'dry_run',
        'comms.enabled' => false, 'comms.email' => false,
        'ai.enabled' => true,
    ]);

    $all = app(AppSettingsService::class)->all();

    expect($all['billing_driver_live'])->toBeFalse()
        ->and($all['comms_enabled'])->toBeFalse()
        ->and($all['comms_email'])->toBeFalse()
        ->and($all['ai_enabled'])->toBeTrue();
});

test('turning both billing flags live flips the resolved gateway', function () {
    config(['billing.driver' => 'dry_run', 'billing.processing_mode' => 'dry_run']);
    expect(app(PaymentGateway::class))->toBeInstanceOf(DryRunPaymentGateway::class);

    Livewire::test(Settings::class)
        ->set('s.billing_driver_live', true)
        ->set('s.billing_processing_live', true)
        ->call('save')
        ->assertHasNoErrors();

    app()->forgetInstance(PaymentGateway::class);
    expect(config('billing.driver'))->toBe('live')
        ->and(config('billing.processing_mode'))->toBe('live')
        ->and(app(PaymentGateway::class))->toBeInstanceOf(LivePaymentGateway::class);
});

test('one billing flag live is not enough to leave dry-run', function () {
    config(['billing.driver' => 'dry_run', 'billing.processing_mode' => 'dry_run']);

    Livewire::test(Settings::class)
        ->set('s.billing_driver_live', true)
        ->set('s.billing_processing_live', false)
        ->call('save');

    app()->forgetInstance(PaymentGateway::class);
    expect(app(AppSettingsService::class)->billingIsLive())->toBeFalse()
        ->and(app(PaymentGateway::class))->toBeInstanceOf(DryRunPaymentGateway::class);
});

test('the comms master switch keeps the SMS gateway null even with every other flag on', function () {
    config([
        'comms.enabled' => false, 'comms.sms' => true, 'telephony.sms_enabled' => true,
    ]);
    app()->forgetInstance(SmsGateway::class);

    expect(app(SmsGateway::class))->toBeInstanceOf(NullSmsGateway::class);

    // Flip the master on through the settings service.
    app(AppSettingsService::class)->save([
        'comms_enabled' => true, 'comms_sms' => true, 'telephony_sms' => true,
    ]);
    app()->forgetInstance(SmsGateway::class);

    expect(app(SmsGateway::class))->not->toBeInstanceOf(NullSmsGateway::class);
});

test('stored settings re-apply on a fresh request', function () {
    app(AppSettingsService::class)->save(['billing_driver_live' => true, 'billing_processing_live' => true]);

    config(['billing.driver' => 'dry_run', 'billing.processing_mode' => 'dry_run']); // simulate a fresh boot
    app(AppSettingsService::class)->apply();

    expect(config('billing.driver'))->toBe('live')
        ->and(config('billing.processing_mode'))->toBe('live');
});
