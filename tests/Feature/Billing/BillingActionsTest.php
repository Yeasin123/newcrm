<?php

use App\Livewire\Customers\Show;
use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\User;
use App\Services\Billing\BillingActionService;
use App\Services\Billing\PaymentInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function actionMerchant(): AuthorizeAccount
{
    return AuthorizeAccount::create([
        'name' => 'M', 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'weight' => 100,
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->superAdmin()->create());
    actionMerchant();
});

test('a custom charge records an is_custom payment row', function () {
    $client = Client::factory()->create();
    CardProfile::create([
        'client_id' => $client->id, 'authorize_account_id' => AuthorizeAccount::first()->id,
        'customer_profile_id' => 'CP', 'payment_profile_id' => 'PP', 'last4' => '4242',
    ]);

    $history = app(BillingActionService::class)->chargeCustom($client, 25.50, 'Deductible top-up');

    expect((float) $history->amount)->toBe(25.5)
        ->and($history->is_custom)->toBeTrue()
        ->and($history->claim_payment)->toBeFalse()
        ->and((int) $history->payment_status)->toBe(1)
        ->and($history->transaction_id)->toStartWith('DRYRUN-TXN-');
});

test('a custom charge fails cleanly when there is no card profile', function () {
    $client = Client::factory()->create();

    expect(fn () => app(BillingActionService::class)->chargeCustom($client, 10, 'x'))
        ->toThrow(RuntimeException::class, 'no saved card profile');
});

test('a same-day payment is voided, an older one is refunded', function () {
    // can_void depends on the wall clock (void until 21:05 on the created date),
    // so pin "now" to the morning of a fixed day for a deterministic result.
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

    $client = Client::factory()->create();

    $today = PaymentHistory::create([
        'client_id' => $client->id, 'amount' => 40, 'payment_status' => 1, 'status' => 1,
        'transaction_id' => 'TXN-TODAY', 'authorize_account_id' => AuthorizeAccount::first()->id,
        'card_number' => '4242',
    ]);
    $today->created_at = Carbon::today()->setTime(9, 0);
    $today->save();

    $old = PaymentHistory::create([
        'client_id' => $client->id, 'amount' => 40, 'payment_status' => 1, 'status' => 1,
        'transaction_id' => 'TXN-OLD', 'authorize_account_id' => AuthorizeAccount::first()->id,
        'card_number' => '4242',
    ]);
    $old->created_at = Carbon::now()->subDays(5);
    $old->save();

    $svc = app(BillingActionService::class);

    expect($svc->voidOrRefund($today->fresh())['action'])->toBe('void')
        ->and($svc->voidOrRefund($old->fresh())['action'])->toBe('refund');

    $today->refresh();
    $old->refresh();
    expect((float) $today->refund_amount)->toBe(40.0)  // void reverses the whole charge
        ->and($today->refund_payment)->toBe(1)
        ->and($old->refund_payment)->toBe(1);
});

test('cancelling a policy cancels the subscription and takes the customer offline', function () {
    $client = Client::factory()->create(['status' => 1, 'login_off' => false]);
    PaymentHistory::create([
        'client_id' => $client->id, 'amount' => 20, 'payment_status' => 1,
        'subscription_id' => 'SUB-CANCEL', 'authorize_account_id' => AuthorizeAccount::first()->id,
    ]);

    $result = app(BillingActionService::class)->cancelPolicy($client, 'Customer request');

    $client->refresh();
    expect($result)->toBe('ok')
        ->and($client->status)->toBe(0)
        ->and((bool) $client->login_off)->toBeTrue()
        ->and($client->cancle_date)->not->toBeNull();
});

test('addPaymentMethod links a new card profile to the customer', function () {
    $client = Client::factory()->create();

    $profile = app(BillingActionService::class)->addPaymentMethod($client, PaymentInput::fromArray([
        'payment_type' => '0',
        'card_number' => '4111111111111111',
        'expMonth' => '12',
        'expYear' => '2030',
    ]));

    expect((int) $profile->client_id)->toBe($client->id)
        ->and($profile->customer_profile_id)->toStartWith('DRYRUN-CP-')
        ->and($profile->payment_profile_id)->toStartWith('DRYRUN-PP-')
        ->and($profile->last4)->toBe('1111')
        ->and((bool) $profile->is_default)->toBeTrue()
        ->and((int) $profile->authorize_account_id)->toBe(AuthorizeAccount::first()->id);
});

test('addPaymentMethod stores a bank instrument without a CIM profile', function () {
    $client = Client::factory()->create();

    $profile = app(BillingActionService::class)->addPaymentMethod($client, PaymentInput::fromArray([
        'payment_type' => '1',
        'routingNumber' => '021000021',
        'accountNumber' => '123456789',
        'bankAccountName' => 'Marcus Rowe',
        'bankAccountType' => 'Checking',
    ]));

    expect((int) $profile->client_id)->toBe($client->id)
        ->and($profile->customer_profile_id)->toBeNull()
        ->and($profile->routingNumber)->toBe('021000021')
        ->and($profile->accountNumber)->toBe('6789')
        ->and($profile->bankAccountName)->toBe('Marcus Rowe');
});

test('the customer screen can charge, reverse a payment and cancel the policy', function () {
    $client = Client::factory()->create(['status' => 1]);
    CardProfile::create([
        'client_id' => $client->id, 'authorize_account_id' => AuthorizeAccount::first()->id,
        'customer_profile_id' => 'CP', 'payment_profile_id' => 'PP', 'last4' => '4242',
    ]);

    Livewire::test(Show::class, ['customer' => $client])
        ->set('chargeAmount', '15')
        ->set('chargeReason', 'Test charge')
        ->call('chargeCustom')
        ->assertHasNoErrors();

    $payment = PaymentHistory::where('client_id', $client->id)->where('is_custom', 1)->first();
    expect($payment)->not->toBeNull();

    Livewire::test(Show::class, ['customer' => $client])
        ->call('reverse', $payment->id);
    expect($payment->fresh()->refund_payment)->toBe(1);

    Livewire::test(Show::class, ['customer' => $client])
        ->set('cancelReason', 'done')
        ->call('cancelPolicy');
    expect($client->fresh()->status)->toBe(0);
});
