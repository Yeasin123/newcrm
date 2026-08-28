<?php

use App\Livewire\FailedPayments\Index;
use App\Livewire\FailedPayments\Settings;
use App\Livewire\FailedPayments\Show;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentCase;
use App\Models\FailedPaymentCommunication;
use App\Models\FailedPaymentNote;
use App\Models\FailedPaymentRetrySchedule;
use App\Models\FailedPaymentSetting;
use App\Models\PaymentHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'failed_payments.module_enabled' => true,
        'failed_payments.capture_enabled' => true,
        'failed_payments.admin_enabled' => true,
        'failed_payments.dry_run_mode' => true,
        'failed_payments.communication_simulation_enabled' => true,
        'failed_payments.retryable_failure_categories' => ['declined'],
        'failed_payments.retry_days' => [1, 3, 7],
        'failed_payments.retry_timezone' => 'UTC',
        'app.timezone' => 'UTC',
    ]);
});

function fp8eCase(array $caseOverrides = []): FailedPaymentCase
{
    $suffix = Str::random(8);
    $account = AuthorizeAccount::create(['name' => 'M'.$suffix, 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k', 'is_active' => true, 'is_disabled' => 0, 'weight' => 100]);
    $client = Client::factory()->monthly()->create(['status' => 0, 'email' => "c{$suffix}@example.com"]);
    $payment = PaymentHistory::create([
        'client_id' => $client->id, 'subscription_id' => 'SUB-'.$suffix, 'amount' => 40, 'currency' => '$',
        'payment_status' => 0, 'status' => 0, 'is_custom' => 0, 'claim_payment' => 0, 'payment_type' => 0,
        'authorize_account_id' => $account->id,
    ]);
    CardProfile::create(['client_id' => $client->id, 'authorize_account_id' => $account->id, 'customer_profile_id' => 'CP', 'payment_profile_id' => 'PP', 'last4' => '4242']);

    $case = FailedPaymentCase::create(array_merge([
        'case_key' => hash('sha256', 'c8e-'.$suffix),
        'client_id' => $client->id, 'payment_history_id' => $payment->id,
        'authorize_account_id' => $account->id, 'arb_subscription_id' => $payment->subscription_id,
        'failed_amount' => 40, 'currency' => '$', 'failure_category' => 'declined',
        'payment_frequency' => 'monthly',
        'first_failed_at' => Carbon::now('UTC')->subDays(2),
        'latest_failed_at' => Carbon::now('UTC')->subDays(2),
        'current_status' => 'open', 'attempt_count' => 1,
    ], $caseOverrides));

    FailedPaymentRetrySchedule::create([
        'failed_payment_case_id' => $case->id, 'sequence_number' => 1, 'retry_delay_days' => 1,
        'scheduled_at' => Carbon::now('UTC')->subHour(), 'status' => 'pending',
    ]);

    return $case->fresh();
}

test('the index lists cases for a permitted admin and 404s when the module is off', function () {
    $this->actingAs(User::factory()->withRole(['failed-payments.view'])->create());
    $case = fp8eCase();
    $name = trim($case->client->first_name.' '.$case->client->last_name);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee($name)
        ->set('status', 'recovered')
        ->assertDontSee($name)
        ->set('status', 'open')
        ->assertSee($name);

    config(['failed_payments.admin_enabled' => false]);
    Livewire::test(Index::class)->assertStatus(404);
});

test('running retries from the index is refused without the retry-control permission', function () {
    $this->actingAs(User::factory()->withRole(['failed-payments.view'])->create());
    fp8eCase();

    Livewire::test(Index::class)->call('runRetries')->assertForbidden();
});

test('the show screen assigns, transitions and takes notes through the workflow service', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $case = fp8eCase();

    $component = Livewire::test(Show::class, ['failedPaymentCase' => $case->case_uuid])
        ->assertOk()
        ->set('assigned_user_id', $admin->id)
        ->call('saveAssignment');

    expect($case->fresh()->assigned_user_id)->toBe($admin->id);

    $component->call('transitionCase', 'manually_resolved');
    expect($case->fresh()->current_status)->toBe('resolved');

    $component->set('noteBody', 'Customer promised to update the card tonight.')
        ->set('noteType', 'internal')
        ->call('addNote');

    $note = FailedPaymentNote::where('failed_payment_case_id', $case->id)->firstOrFail();
    expect($note->note_body)->toContain('update the card')
        ->and(FailedPaymentAuditLog::where('failed_payment_case_id', $case->id)->where('action', 'note_created')->exists())->toBeTrue();

    $component->call('editNote', $note->id)->set('noteBody', 'Edited note body.')->call('addNote');
    expect($note->fresh()->note_body)->toBe('Edited note body.');

    $component->call('deleteNote', $note->id);
    expect(FailedPaymentNote::where('failed_payment_case_id', $case->id)->exists())->toBeFalse();
});

test('the show screen controls retry schedules and runs a dry-run simulation without charging', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $case = fp8eCase();
    $schedule = $case->retrySchedules()->firstOrFail();

    $component = Livewire::test(Show::class, ['failedPaymentCase' => $case->case_uuid])
        ->call('simulateRetry', $schedule->id)
        ->assertSet('lastSimResult', fn ($r) => ($r['status'] ?? null) === 'simulated' && ($r['attempt_result'] ?? null) === 'dry_run');

    $schedule->refresh();
    expect($schedule->status)->toBe('pending') // simulation never advances the schedule
        ->and(data_get($schedule->metadata, 'last_attempt_result'))->toBe('dry_run')
        ->and(FailedPaymentAuditLog::where('failed_payment_case_id', $case->id)->where('action', 'retry_simulated')->exists())->toBeTrue();
    Http::assertNothingSent();

    $component->call('controlRetry', $schedule->id, 'pause');
    expect($schedule->fresh()->status)->toBe('paused');

    $component->call('controlRetry', $schedule->id, 'cancel');
    expect($schedule->fresh()->status)->toBe('cancelled');
});

test('the show screen previews and dry-run-simulates a communication but refuses a live send', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $case = fp8eCase();

    $component = Livewire::test(Show::class, ['failedPaymentCase' => $case->case_uuid])
        ->call('previewCommunication')
        ->assertSet('commPreview', fn ($p) => is_array($p) && ($p['message'] ?? '') !== '');

    $component->call('simulateCommunication');
    $sim = FailedPaymentCommunication::where('failed_payment_case_id', $case->id)->firstOrFail();
    expect($sim->status)->toBe('simulated')
        ->and($sim->channel)->toBe('email');

    // live email is disabled by default -> the send is refused and no 'sent' row appears
    $component->call('sendCommunication');
    expect(FailedPaymentCommunication::where('failed_payment_case_id', $case->id)->where('status', 'sent')->exists())->toBeFalse();
});

test('the settings screen round-trips a feature flag into config and the settings table', function () {
    $this->actingAs(User::factory()->withRole(['failed-payments.settings'])->create());

    Livewire::test(Settings::class)
        ->assertOk()
        ->assertSet('automatic_retry_enabled', false)
        ->set('automatic_retry_enabled', true)
        ->set('retry_days', '2, 5, 9')
        ->call('save');

    expect(config('failed_payments.automatic_retry_enabled'))->toBeTrue()
        ->and(config('failed_payments.retry_days'))->toBe([2, 5, 9])
        ->and(FailedPaymentSetting::where('setting_key', 'automatic_retry_enabled')->value('setting_value'))->toBe(['value' => true]);
});

test('a user without failed-payments permission cannot open the module screens', function () {
    $this->actingAs(User::factory()->withRole(['Customers'])->create());
    $case = fp8eCase();

    Livewire::test(Index::class)->assertForbidden();
    Livewire::test(Show::class, ['failedPaymentCase' => $case->case_uuid])->assertForbidden();
    Livewire::test(Settings::class)->assertForbidden();
});
