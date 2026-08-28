<?php

use App\Enums\FailedPaymentCaseStatus;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentRetrySchedule;
use App\Models\FailedPaymentStatusHistory;
use App\Models\FailedPaymentWebhookEvent;
use App\Models\PaymentHistory;
use App\Services\FailedPayment\FailedPaymentRecoveryService;
use App\Services\FailedPayment\FailedPaymentRetryScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'failed_payments.module_enabled' => true,
        'failed_payments.capture_enabled' => true,
        'failed_payments.dry_run_mode' => true,
        'failed_payments.retry_timezone' => 'UTC',
        'app.timezone' => 'UTC',
        'failed_payments.retryable_failure_categories' => ['declined', 'suspended', 'insufficient_funds'],
        'failed_payments.retry_days' => [1, 3, 7, 14, 30],
    ]);
});

/**
 * An OPEN failed-payment case with a full retry schedule and every reference
 * row the recovery + live-retry paths need (client, merchant account, unpaid
 * payment_histories row, saved CIM card profile).
 */
function fpRecoverable(array $caseOverrides = []): array
{
    $suffix = Str::random(8);

    $client = Client::factory()->monthly()->create(['status' => 0, 'first_name' => 'Rec'.$suffix]);

    $account = AuthorizeAccount::create([
        'name' => 'M'.$suffix, 'environment' => 'sandbox',
        'api_login_id' => 'l', 'transaction_key' => 'k', 'signature_key' => 's',
        'is_active' => true, 'is_disabled' => 0, 'weight' => 100,
    ]);

    $payment = PaymentHistory::create([
        'client_id' => $client->id, 'subscription_id' => 'SUB-'.$suffix,
        'transaction_id' => 'TX-'.$suffix, 'amount' => 40, 'currency' => '$',
        'payment_date' => '2026-07-28 09:00:00', 'payment_status' => 0, 'status' => 0,
        'is_custom' => 0, 'claim_payment' => 0, 'payment_type' => 0,
        'authorize_account_id' => $account->id,
    ]);

    CardProfile::create([
        'client_id' => $client->id, 'authorize_account_id' => $account->id,
        'customer_profile_id' => 'CP-'.$suffix, 'payment_profile_id' => 'PP-'.$suffix, 'last4' => '4242',
    ]);

    $case = FailedPaymentCase::create(array_merge([
        'case_key' => hash('sha256', 'rec-'.$suffix),
        'client_id' => $client->id, 'payment_history_id' => $payment->id,
        'authorize_account_id' => $account->id,
        'arb_subscription_id' => $payment->subscription_id,
        'anet_transaction_id' => $payment->transaction_id,
        'failed_amount' => 40, 'currency' => '$',
        'failure_category' => 'declined', 'failure_code' => '2', 'failure_reason' => 'Declined',
        'first_failed_at' => Carbon::parse('2026-07-28 09:00:00', 'UTC'),
        'latest_failed_at' => Carbon::parse('2026-07-28 09:00:00', 'UTC'),
        'current_status' => FailedPaymentCaseStatus::OPEN,
        'attempt_count' => 1,
    ], $caseOverrides));

    app(FailedPaymentRetryScheduleService::class)->createForCase($case->fresh());

    return [$account, $client, $payment, $case->fresh()];
}

function fpVerifiedSuccessEvent(FailedPaymentCase $case, array $overrides = []): FailedPaymentWebhookEvent
{
    $eventId = $overrides['event_id'] ?? 'evt-ok-'.Str::random(8);
    $payload = array_replace_recursive([
        'authorize_account_id' => $case->authorize_account_id,
        'arb_subscription_id' => $case->arb_subscription_id,
        'payment_history_id' => $case->payment_history_id,
    ], $overrides['payload'] ?? []);

    return FailedPaymentWebhookEvent::create([
        'provider' => 'authorize_net',
        'event_id' => $eventId,
        'event_type' => $overrides['event_type'] ?? 'transaction.settled',
        'payload_hash' => hash('sha256', json_encode($payload) ?: $eventId),
        'sanitized_payload' => $payload,
        'signature_validation_status' => $overrides['signature_validation_status'] ?? 'valid',
        'received_at' => $overrides['received_at'] ?? Carbon::parse('2026-07-30 11:30:00', 'UTC'),
        'processed_at' => $overrides['processed_at'] ?? Carbon::parse('2026-07-30 11:35:00', 'UTC'),
        'processing_status' => $overrides['processing_status'] ?? 'processed',
        'failed_payment_case_id' => null,
        'metadata' => [],
    ]);
}

test('recoverFromVerifiedSuccess marks the case recovered and cancels remaining schedules', function () {
    [$account, $client, $payment, $case] = fpRecoverable();
    $event = fpVerifiedSuccessEvent($case);

    $before = [Client::count(), PaymentHistory::count(), AuthorizeAccount::count()];

    $result = app(FailedPaymentRecoveryService::class)->recoverFromVerifiedSuccess([
        'provider' => 'authorize_net',
        'event_id' => $event->event_id,
        'failed_payment_webhook_event_id' => $event->id,
        'event_type' => $event->event_type,
        'source' => 'verified_success',
    ]);

    $case->refresh();
    $event->refresh();

    expect($result['status'])->toBe('recovered')
        ->and($case->current_status)->toBe(FailedPaymentCaseStatus::RECOVERED)
        ->and($case->recovered_at)->not->toBeNull()
        ->and(FailedPaymentRetrySchedule::where('failed_payment_case_id', $case->id)->where('status', FailedPaymentRetryScheduleStatus::CANCELLED)->count())->toBe(5)
        ->and(FailedPaymentStatusHistory::where('failed_payment_case_id', $case->id)->where('new_status', FailedPaymentCaseStatus::RECOVERED)->count())->toBe(1)
        ->and(FailedPaymentAuditLog::where('failed_payment_case_id', $case->id)->where('action', 'recovery_recorded')->count())->toBe(1)
        ->and($event->failed_payment_case_id)->toBe($case->id)
        ->and($event->processing_status)->toBe('processed')
        // recovery must never insert into the shared reference tables
        ->and([Client::count(), PaymentHistory::count(), AuthorizeAccount::count()])->toBe($before);

    $payment->refresh();
    expect((int) $payment->payment_status)->toBe(1);
});

test('recoverFromVerifiedSuccess refuses an unverified (bad signature) event', function () {
    [, , , $case] = fpRecoverable();
    $event = fpVerifiedSuccessEvent($case, ['signature_validation_status' => 'invalid']);

    $result = app(FailedPaymentRecoveryService::class)->recoverFromVerifiedSuccess([
        'provider' => 'authorize_net',
        'event_id' => $event->event_id,
        'failed_payment_webhook_event_id' => $event->id,
        'event_type' => $event->event_type,
    ]);

    expect($result['status'])->toBe('unmatched')
        ->and($result['reason_code'])->toBe('unverified_success_event')
        ->and($case->fresh()->current_status)->toBe(FailedPaymentCaseStatus::OPEN);
});

test('recoverFromVerifiedSuccess refuses when two open cases share the same evidence', function () {
    [$account, $client, $payment, $case] = fpRecoverable();
    $twin = FailedPaymentCase::create([
        'case_key' => hash('sha256', 'twin-'.Str::random(6)),
        'client_id' => $client->id, 'payment_history_id' => $payment->id,
        'authorize_account_id' => $account->id, 'arb_subscription_id' => $case->arb_subscription_id,
        'anet_transaction_id' => 'TX-TWIN', 'failed_amount' => 40, 'currency' => '$',
        'failure_category' => 'declined',
        'first_failed_at' => Carbon::parse('2026-07-28 09:00:00', 'UTC'),
        'latest_failed_at' => Carbon::parse('2026-07-28 09:00:00', 'UTC'),
        'current_status' => FailedPaymentCaseStatus::OPEN, 'attempt_count' => 1,
    ]);
    $event = fpVerifiedSuccessEvent($case);

    $result = app(FailedPaymentRecoveryService::class)->recoverFromVerifiedSuccess([
        'provider' => 'authorize_net',
        'event_id' => $event->event_id,
        'failed_payment_webhook_event_id' => $event->id,
        'event_type' => $event->event_type,
    ]);

    expect($result['status'])->toBe('unmatched')
        ->and($result['reason_code'])->toBe('ambiguous_match')
        ->and($case->fresh()->current_status)->toBe(FailedPaymentCaseStatus::OPEN)
        ->and($twin->fresh()->current_status)->toBe(FailedPaymentCaseStatus::OPEN);
});

test('the process-retries command charges the CIM profile, recovers the case and re-establishes the ARB subscription', function () {
    config([
        'failed_payments.dry_run_mode' => false,
        'failed_payments.retry_gateway_driver' => 'live_cim',
        'failed_payments.retry_processing_mode' => 'live',
        'failed_payments.manual_retry_enabled' => true,
        'failed_payments.automatic_retry_enabled' => true,
    ]);

    Http::fake(['*authorize.net/*' => Http::response(json_encode([
        'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001', 'text' => 'ok']]],
        'transactionResponse' => ['responseCode' => '1', 'transId' => 'TXN-8D-1', 'authCode' => 'OK'],
        'subscriptionId' => 'ARB-8D-1',
    ]), 200)]);

    [$account, $client, $payment, $case] = fpRecoverable([
        // due now so the cron picks it up
        'first_failed_at' => Carbon::now('UTC')->subDays(2),
        'latest_failed_at' => Carbon::now('UTC')->subDays(2),
    ]);

    // the sequence-1 schedule is due (failed_at + 1 day < now)
    $due = FailedPaymentRetrySchedule::where('failed_payment_case_id', $case->id)
        ->where('sequence_number', 1)->firstOrFail();
    expect($due->scheduled_at->lessThan(now()))->toBeTrue();

    $this->artisan('failed-payments:process-retries')->assertSuccessful();

    $case->refresh();
    $payment->refresh();
    $client->refresh();

    expect($case->current_status)->toBe(FailedPaymentCaseStatus::RECOVERED)
        ->and($due->fresh()->status)->toBe(FailedPaymentRetryScheduleStatus::COMPLETED)
        ->and((int) $payment->payment_status)->toBe(1)
        ->and($payment->subscription_id)->toBe('ARB-8D-1')
        ->and((int) $client->status)->toBe(1)
        // sequences 2..5 were cancelled by the recovery
        ->and(FailedPaymentRetrySchedule::where('failed_payment_case_id', $case->id)->where('status', FailedPaymentRetryScheduleStatus::CANCELLED)->count())->toBe(4)
        ->and($case->metadata['arb_subscription_created_status'] ?? null)->toBe('created');
});

test('the process-retries command refuses to run without the live flags', function () {
    config([
        'failed_payments.dry_run_mode' => false,
        'failed_payments.retry_gateway_driver' => 'dry_run',
        'failed_payments.retry_processing_mode' => 'dry_run',
    ]);

    $this->artisan('failed-payments:process-retries')->assertFailed();
});

test('the process-retries --simulate run refuses when dry-run mode is off', function () {
    config(['failed_payments.dry_run_mode' => false]);

    $this->artisan('failed-payments:process-retries --simulate')->assertFailed();
});
