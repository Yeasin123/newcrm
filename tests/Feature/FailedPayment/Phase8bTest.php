<?php

use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Models\FailedPaymentAttempt;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentRetrySchedule;
use App\Models\FailedPaymentWebhookEvent;
use App\Models\PaymentHistory;
use App\Services\FailedPayment\FailedPaymentWebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const FP_SIG = 'FEEDFACEFEEDFACEFEEDFACEFEEDFACE';

function fpMerchant(): AuthorizeAccount
{
    return AuthorizeAccount::create([
        'name' => 'M', 'environment' => 'sandbox',
        'api_login_id' => 'l', 'transaction_key' => 'k', 'signature_key' => FP_SIG,
        'is_active' => true, 'weight' => 100,
    ]);
}

function fpSuspend(string $subsId, ?string $eventId = null): TestResponse
{
    $body = [
        'id' => $eventId ?? 'evt-'.Str::random(8),
        'eventType' => 'net.authorize.customer.subscription.suspended',
        'eventDate' => now()->toIso8601String(),
        'payload' => ['id' => $subsId, 'amount' => 49.99],
    ];
    $raw = json_encode($body);

    return test()->call('POST', '/api/transaction/suspend/notify', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ANET_SIGNATURE' => 'sha512='.hash_hmac('sha512', $raw, FP_SIG),
    ], $raw);
}

function fpSeedPayment(Client $client, AuthorizeAccount $acc, string $subsId): PaymentHistory
{
    return PaymentHistory::create([
        'client_id' => $client->id, 'subscription_id' => $subsId, 'amount' => 49.99,
        'payment_status' => 1, 'status' => 1, 'claim_payment' => 0, 'is_custom' => 0,
        'payment_type' => 0, 'authorize_account_id' => $acc->id,
        'payment_date' => now()->subDays(3)->format('Y-m-d H:i:s'), 'currency' => '$',
    ]);
}

beforeEach(fn () => Http::preventStrayRequests());

test('with the module off, the webhook event is still recorded but no case is created', function () {
    $acc = fpMerchant();
    $client = Client::factory()->monthly()->create(['status' => 1]);
    fpSeedPayment($client, $acc, 'sub-off');

    fpSuspend('sub-off')->assertOk();

    expect(FailedPaymentWebhookEvent::where('provider', 'authorize_net')->exists())->toBeTrue()
        ->and(FailedPaymentCase::count())->toBe(0);
});

test('with capture enabled, a suspend webhook opens a failed payment case', function () {
    config(['failed_payments.module_enabled' => true, 'failed_payments.capture_enabled' => true]);

    $acc = fpMerchant();
    $client = Client::factory()->monthly()->create(['status' => 1]);
    $payment = fpSeedPayment($client, $acc, 'sub-cap');

    fpSuspend('sub-cap')->assertOk();

    $case = FailedPaymentCase::first();
    expect($case)->not->toBeNull()
        ->and($case->client_id)->toBe($client->id)
        ->and($case->payment_history_id)->toBe($payment->id)
        ->and($case->failure_category)->toBe('suspended')
        ->and((float) $case->failed_amount)->toBe(49.99)
        ->and($case->attempt_count)->toBe(1)
        ->and(FailedPaymentAttempt::where('failed_payment_case_id', $case->id)->exists())->toBeTrue()
        ->and($case->statusHistories()->count())->toBeGreaterThanOrEqual(1);
});

test('a re-delivered webhook (same event id) does not open a second case', function () {
    config(['failed_payments.module_enabled' => true, 'failed_payments.capture_enabled' => true]);

    $acc = fpMerchant();
    $client = Client::factory()->monthly()->create(['status' => 1]);
    fpSeedPayment($client, $acc, 'sub-dup');

    fpSuspend('sub-dup', 'evt-fixed')->assertOk()->assertJson(['status' => 'success']);
    fpSuspend('sub-dup', 'evt-fixed')->assertOk()->assertJson(['status' => 'duplicate_ignored']);

    expect(FailedPaymentCase::count())->toBe(1);
});

test('the webhook recorder redacts sensitive fields', function () {
    $event = app(FailedPaymentWebhookService::class)->recordReceived([
        'provider' => 'authorize_net',
        'event_id' => 'evt-redact',
        'event_type' => 'x',
        'payload' => ['card_number' => '4111111111111111', 'authAmount' => 10, 'nested' => ['cvv' => '123']],
        'signature_validation_status' => 'valid',
    ]);

    $sanitized = $event->sanitized_payload;
    expect($sanitized['card_number'])->toBe('[redacted]')
        ->and($sanitized['nested']['cvv'])->toBe('[redacted]')
        ->and($sanitized['authAmount'])->toBe(10);
});

test('an eligible category seeds a retry schedule when automatic retry is on', function () {
    config([
        'failed_payments.module_enabled' => true,
        'failed_payments.capture_enabled' => true,
        'failed_payments.automatic_retry_enabled' => true,
        'failed_payments.retryable_failure_categories' => ['suspended'],
        'failed_payments.retry_days' => [1, 3, 7],
    ]);

    $acc = fpMerchant();
    $client = Client::factory()->monthly()->create(['status' => 1]);
    fpSeedPayment($client, $acc, 'sub-retry');

    fpSuspend('sub-retry')->assertOk();

    $case = FailedPaymentCase::first();
    expect(FailedPaymentRetrySchedule::where('failed_payment_case_id', $case->id)->count())->toBeGreaterThan(0);
});
