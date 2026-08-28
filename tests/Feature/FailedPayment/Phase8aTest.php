<?php

use App\Enums\FailedPaymentCaseStatus;
use App\Models\FailedPaymentAttempt;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentNote;
use App\Models\FailedPaymentSetting;
use App\Models\User;
use App\Services\FailedPayment\FailedPaymentSettingsService;

test('a failed payment case gets a uuid and default status on create', function () {
    $case = FailedPaymentCase::create(['case_key' => 'ck-1']);

    expect($case->case_uuid)->toMatch('/^[0-9a-f-]{36}$/i')
        ->and($case->current_status)->toBe(FailedPaymentCaseStatus::NEW)
        ->and($case->priority)->toBe('normal')
        ->and($case->getRouteKeyName())->toBe('case_uuid');
});

test('case relationships resolve', function () {
    $case = FailedPaymentCase::create(['case_key' => 'ck-2']);
    FailedPaymentAttempt::create([
        'failed_payment_case_id' => $case->id, 'attempt_number' => 1,
        'attempt_type' => 'webhook_failure', 'attempt_source' => 'authorize_net_webhook',
        'status' => 'recorded', 'idempotency_key' => 'idem-1',
    ]);
    FailedPaymentNote::create(['failed_payment_case_id' => $case->id, 'note_body' => 'hi']);

    $case->load(['attempts', 'notes']);
    expect($case->attempts)->toHaveCount(1)
        ->and($case->notes)->toHaveCount(1);
});

test('the settings service defaults keep the module inert', function () {
    $defaults = app(FailedPaymentSettingsService::class)->defaults();

    expect($defaults['dry_run_mode'])->toBeTrue()
        ->and($defaults['automatic_retry_enabled'])->toBeFalse()
        ->and($defaults['manual_retry_enabled'])->toBeFalse()
        ->and($defaults['email_enabled'])->toBeFalse()
        ->and($defaults['retry_days'])->toBe([1, 3, 7, 14, 30]);
});

test('save() writes failed_payment_settings rows and overlays config', function () {
    app(FailedPaymentSettingsService::class)->save([
        'module_enabled' => true,
        'automatic_retry_enabled' => true,
        'retry_days' => '2,5,9',
        'retry_timezone' => 'America/New_York',
    ], adminId: 7);

    expect(FailedPaymentSetting::where('setting_key', 'module_enabled')->exists())->toBeTrue()
        ->and(config('failed_payments.automatic_retry_enabled'))->toBeTrue()
        ->and(config('failed_payments.retry_days'))->toBe([2, 5, 9])
        ->and(config('failed_payments.retry_timezone'))->toBe('America/New_York');
});

test('syncConfig() re-applies stored settings onto config', function () {
    FailedPaymentSetting::create(['setting_key' => 'manual_retry_enabled', 'setting_value' => ['value' => true], 'is_active' => true]);

    config(['failed_payments.manual_retry_enabled' => false]);
    app(FailedPaymentSettingsService::class)->syncConfig();

    expect(config('failed_payments.manual_retry_enabled'))->toBeTrue();
});

test('the FailedPaymentCase policy honours failed-payments.* permissions and superadmin', function () {
    $super = User::factory()->superAdmin()->create();
    $viewer = User::factory()->withRole(['failed-payments.view'])->create();
    $nobody = User::factory()->withRole(['Customers'])->create();
    $case = FailedPaymentCase::create(['case_key' => 'ck-pol']);

    expect($super->can('viewAny', FailedPaymentCase::class))->toBeTrue()
        ->and($viewer->can('viewAny', FailedPaymentCase::class))->toBeTrue()
        ->and($viewer->can('update', $case))->toBeFalse()
        ->and($nobody->can('viewAny', FailedPaymentCase::class))->toBeFalse();
});
