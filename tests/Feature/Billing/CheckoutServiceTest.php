<?php

use App\Models\AuthorizeAccount;
use App\Models\Bonus;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\PaymentLink;
use App\Models\SaleCommission;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentInput;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function merchant(array $o = []): AuthorizeAccount
{
    return AuthorizeAccount::create(array_merge([
        'name' => 'Primary', 'environment' => 'sandbox',
        'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'weight' => 100,
    ], $o));
}

function opaqueInput(): PaymentInput
{
    return PaymentInput::fromArray([
        'payment_type' => '0',
        'card_number' => '4111111111111111',
        'expMonth' => '12', 'expYear' => (string) (now()->year + 3),
        'opaqueDataDescriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
        'opaqueDataValue' => 'tok_'.Str::random(20),
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests(); // dry-run must never call out
    // Pin the clock to midday so the after-hours commission multiplier
    // (Commission Settings) does not perturb the commission assertions.
    $this->travelTo(now()->setTime(12, 0, 0));
    merchant();
});

test('a monthly checkout charges, subscribes and marks the client active', function () {
    $client = Client::factory()->monthly()->create(['status' => 0, 'bypass_payment' => 1, 'salesman_name' => null]);

    $history = app(CheckoutService::class)->run($client, 49.99, opaqueInput());

    $client->refresh();
    expect($client->status)->toBe(1)
        ->and($client->bypass_payment)->toBe(0)
        ->and($client->start_date)->not->toBeNull()
        ->and($history->payment_status)->toBe(1)
        ->and((float) $history->amount)->toBe(49.99)
        ->and($history->subscription_id)->toStartWith('DRYRUN-SUB-')
        ->and(CardProfile::where('client_id', $client->id)->count())->toBe(1);
});

test('the new subscription id is stamped onto every existing history row', function () {
    $client = Client::factory()->monthly()->create();
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 10, 'payment_status' => 1]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 10, 'payment_status' => 0]);

    app(CheckoutService::class)->run($client, 20, opaqueInput());

    $subs = PaymentHistory::where('client_id', $client->id)->pluck('subscription_id')->unique();
    expect($subs)->toHaveCount(1)
        ->and($subs->first())->toStartWith('DRYRUN-SUB-');
});

test('a yearly-with-installment checkout uses the remaining count and bumps paid_installment', function () {
    $client = Client::factory()->yearly()->create(['installment' => '1', 'paid_installment' => 1]);

    app(CheckoutService::class)->run($client, 120, opaqueInput(), ['installment_count' => 4]);

    // installment_no = 2, so 2 remaining occurrences; paid_installment -> 2
    expect($client->fresh()->paid_installment)->toBe(2)
        ->and(PaymentHistory::where('client_id', $client->id)->value('purpose'))->toContain('Installment - Monthly');
});

test('an existing card profile is reused instead of creating a new one', function () {
    $client = Client::factory()->monthly()->create();
    CardProfile::create([
        'client_id' => $client->id, 'authorize_account_id' => AuthorizeAccount::first()->id,
        'customer_profile_id' => 'CP-EXISTING', 'payment_profile_id' => 'PP-EXISTING',
        'last4' => '1111', 'brand' => 'Visa',
    ]);

    app(CheckoutService::class)->run($client, 30, opaqueInput());

    expect(CardProfile::where('client_id', $client->id)->count())->toBe(1);
});

test('a matched salesman gets a commission of amount minus ten percent', function () {
    $salesman = User::factory()->create(['first_name' => 'Sky', 'last_name' => 'Seller']);
    $client = Client::factory()->monthly()->create(['salesman_name' => 'Sky Seller']);

    app(CheckoutService::class)->run($client, 100, opaqueInput());

    $commission = SaleCommission::where('client_id', $client->id)->first();
    expect((float) $commission->commision_price)->toBe(90.0)
        ->and($commission->user_id)->toBe($salesman->id);
});

test('bonuses.commission_double doubles the commission', function () {
    Bonus::create(['commission_double' => true]);
    $salesman = User::factory()->create(['first_name' => 'Deb', 'last_name' => 'Double']);
    $client = Client::factory()->monthly()->create(['salesman_name' => 'Deb Double']);

    app(CheckoutService::class)->run($client, 100, opaqueInput());

    expect((float) SaleCommission::where('client_id', $client->id)->value('commision_price'))->toBe(180.0);
});

test('a supplied payment link token is expired on success', function () {
    $client = Client::factory()->monthly()->create();
    $link = PaymentLink::create(['client_id' => $client->id, 'token' => 'tok-abc', 'status' => '1']);

    app(CheckoutService::class)->run($client, 25, opaqueInput(), ['payment_link_token' => 'tok-abc']);

    expect($link->fresh()->status)->toBe('0');
});

test('a bank checkout charges and subscribes without a card profile', function () {
    $client = Client::factory()->monthly()->create();
    $bankInput = PaymentInput::fromArray([
        'payment_type' => '1',
        'routingNumber' => '021000021', 'accountNumber' => '123456789',
        'bankAccountName' => 'Jane Doe', 'bankAccountType' => 'checking', 'echeckType' => 'WEB',
    ]);

    $history = app(CheckoutService::class)->run($client, 40, $bankInput);

    expect($history->payment_type)->toBe('1')
        ->and($history->subscription_id)->toStartWith('DRYRUN-SUB-')
        ->and(CardProfile::where('client_id', $client->id)->count())->toBe(0)
        ->and($client->fresh()->status)->toBe(1);
});

test('a raw card with no token cannot start a subscription', function () {
    $client = Client::factory()->monthly()->create();
    $raw = PaymentInput::fromArray([
        'payment_type' => '0',
        'card_number' => '4111111111111111', 'expMonth' => '12', 'expYear' => (string) (now()->year + 2),
    ]);

    expect(fn () => app(CheckoutService::class)->run($client, 20, $raw))
        ->toThrow(RuntimeException::class, 'saved card or Accept.js token is required');
});
