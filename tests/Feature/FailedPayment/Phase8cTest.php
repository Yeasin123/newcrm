<?php

use App\Enums\FailedPaymentRetryAttemptResult;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\FailedPaymentAttempt;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentRetrySchedule;
use App\Models\PaymentHistory;
use App\Services\FailedPayment\Contracts\FailedPaymentRetryGatewayInterface;
use App\Services\FailedPayment\DryRunFailedPaymentRetryGateway;
use App\Services\FailedPayment\FailedPaymentRetryEligibilityService;
use App\Services\FailedPayment\FailedPaymentRetryProcessorService;
use App\Services\FailedPayment\LiveFailedPaymentRetryGateway;
use App\Services\FailedPayment\UnavailableFailedPaymentRetryGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'failed_payments.module_enabled' => true,
        'failed_payments.capture_enabled' => true,
        'failed_payments.manual_retry_enabled' => true,
        'failed_payments.retryable_failure_categories' => ['suspended', 'insufficient_funds'],
        'failed_payments.retry_days' => [1, 3, 7],
    ]);
});

function fpCaseWithSchedule(): array
{
    $acc = AuthorizeAccount::create(['name' => 'M', 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k', 'is_active' => true, 'weight' => 100]);
    $client = Client::factory()->monthly()->create(['status' => 0]);
    $payment = PaymentHistory::create([
        'client_id' => $client->id, 'subscription_id' => 'sub-x', 'amount' => 40,
        'payment_status' => 0, 'is_custom' => 0, 'claim_payment' => 0, 'payment_type' => 0,
        'authorize_account_id' => $acc->id,
    ]);
    CardProfile::create([
        'client_id' => $client->id, 'authorize_account_id' => $acc->id,
        'customer_profile_id' => 'CP', 'payment_profile_id' => 'PP', 'last4' => '4242',
    ]);
    $case = FailedPaymentCase::create([
        'case_key' => 'ck-'.Str::random(6),
        'client_id' => $client->id, 'payment_history_id' => $payment->id,
        'authorize_account_id' => $acc->id, 'arb_subscription_id' => 'sub-x',
        'failure_category' => 'suspended', 'failed_amount' => 40,
        'first_failed_at' => now()->subDay(),
    ]);
    $schedule = FailedPaymentRetrySchedule::create([
        'failed_payment_case_id' => $case->id, 'sequence_number' => 1, 'retry_delay_days' => 1,
        'scheduled_at' => now()->subHour(), 'status' => 'pending',
    ]);

    return [$acc, $client, $payment, $case, $schedule];
}

test('the retry gateway is dry-run by default and unavailable when flags are half-set', function () {
    config(['failed_payments.dry_run_mode' => true, 'failed_payments.retry_gateway_driver' => 'dry_run', 'failed_payments.retry_processing_mode' => 'dry_run']);
    expect(app(FailedPaymentRetryGatewayInterface::class))->toBeInstanceOf(DryRunFailedPaymentRetryGateway::class);

    config(['failed_payments.retry_processing_mode' => 'live']); // half-set
    expect(app(FailedPaymentRetryGatewayInterface::class))->toBeInstanceOf(UnavailableFailedPaymentRetryGateway::class);
});

test('both live flags select the live CIM retry gateway', function () {
    config([
        'failed_payments.dry_run_mode' => false,
        'failed_payments.retry_gateway_driver' => 'live_cim',
        'failed_payments.retry_processing_mode' => 'live',
    ]);

    expect(app(FailedPaymentRetryGatewayInterface::class))->toBeInstanceOf(LiveFailedPaymentRetryGateway::class);
});

test('a simulation run records a dry-run result and never charges', function () {
    config(['failed_payments.dry_run_mode' => true, 'failed_payments.retry_gateway_driver' => 'dry_run', 'failed_payments.retry_processing_mode' => 'dry_run']);
    [, , , , $schedule] = fpCaseWithSchedule();

    $result = app(FailedPaymentRetryProcessorService::class)
        ->processSchedule($schedule->fresh(), ['execution_intent' => 'simulation']);

    $schedule->refresh();
    expect($result['status'] ?? null)->toBe('simulated')
        ->and($schedule->status)->toBe(FailedPaymentRetryScheduleStatus::PENDING) // simulation never advances the schedule
        ->and(data_get($schedule->metadata, 'last_attempt_result'))->toBe(FailedPaymentRetryAttemptResult::DRY_RUN);
    Http::assertNothingSent();
});

test('a live manual retry charges the saved CIM profile via the JSON gateway and completes the schedule', function () {
    config([
        'failed_payments.dry_run_mode' => false,
        'failed_payments.retry_gateway_driver' => 'live_cim',
        'failed_payments.retry_processing_mode' => 'live',
    ]);
    Http::fake(['*authorize.net/*' => Http::response(json_encode([
        'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'ok']]],
        'transactionResponse' => ['responseCode' => '1', 'transId' => 'TXN-RETRY-1', 'authCode' => 'OK'],
    ]), 200)]);

    [, , , $case, $schedule] = fpCaseWithSchedule();

    $result = app(FailedPaymentRetryProcessorService::class)
        ->processSchedule($schedule->fresh(), ['execution_intent' => 'manual']);

    $schedule->refresh();
    expect($schedule->status)->toBe(FailedPaymentRetryScheduleStatus::COMPLETED)
        ->and(FailedPaymentAttempt::where('failed_payment_case_id', $case->id)->exists())->toBeTrue();
});

test('eligibility rejects a non-retryable failure category', function () {
    [, , , $case, $schedule] = fpCaseWithSchedule();
    $case->update(['failure_category' => 'suspected_fraud']);

    $decision = app(FailedPaymentRetryEligibilityService::class)->evaluate($schedule->fresh());

    expect($decision->eligible)->toBeFalse()
        ->and($decision->reasonCode)->not->toBe('eligible');
});
