<?php

use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\PlanChangeLog;
use App\Services\Billing\BillingActions;
use App\Services\Billing\NewPlanInput;
use App\Services\Billing\PlanChangeService;
use Illuminate\Support\Facades\Http;

function pcMerchant(): AuthorizeAccount
{
    return AuthorizeAccount::create([
        'name' => 'M', 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'weight' => 100,
    ]);
}

function pcProfile(Client $client): CardProfile
{
    return CardProfile::create([
        'client_id' => $client->id, 'authorize_account_id' => AuthorizeAccount::first()->id,
        'customer_profile_id' => 'CP', 'payment_profile_id' => 'PP', 'last4' => '4242',
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    pcMerchant();
});

test('preview of a yearly full-pay change suggests remaining = price minus paid', function () {
    $client = Client::factory()->yearly()->create([
        'payment_structure' => '2', 'installment' => null,
        'start_date' => now()->subMonths(3)->toDateString(), 'end_date' => now()->addMonths(9)->toDateString(),
    ]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 200, 'payment_status' => 1, 'claim_payment' => 0, 'is_custom' => 0]);

    $preview = app(PlanChangeService::class)->preview($client, new NewPlanInput(paymentTermCode: 3, newTotalPrice: 620));

    expect($preview['transition'])->toBe('to_full_pay')
        ->and($preview['amount_already_paid'])->toBe('200.00')
        ->and($preview['suggested_remaining'])->toBe('420.00')
        ->and($preview['suggested_charge_today'])->toBe('420.00');
});

test('preview of a downgrade to monthly with a big prior balance suggests skip months', function () {
    $client = Client::factory()->yearly()->create([
        'payment_structure' => '2', 'installment' => null,
        'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(11)->toDateString(),
        'price_cal_response' => json_encode(['price' => '600']),
    ]);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 600, 'payment_status' => 1, 'claim_payment' => 0, 'is_custom' => 0]);

    $preview = app(PlanChangeService::class)->preview($client, new NewPlanInput(paymentTermCode: 1, newTotalPrice: 40));

    expect($preview['transition'])->toBe('to_monthly')
        ->and((int) $preview['suggested_skip_months'])->toBeGreaterThan(0)
        ->and($preview['suggested_new_subscription_amount'])->toBe('40.00');
});

test('applyManual with charge-today and create-new writes a completed log and a new subscription', function () {
    $client = Client::factory()->monthly()->create(['payment_structure' => '1']);
    pcProfile($client);

    $result = app(PlanChangeService::class)->applyManual(
        $client,
        new NewPlanInput(paymentTermCode: 3, installmentOn: true, installmentType: 3, newTotalPrice: 900),
        new BillingActions([
            'charge_today' => true, 'charge_today_amount' => 300,
            'create_new_subscription' => true, 'new_subscription_amount' => 200,
            'new_subscription_occurrences' => 3, 'new_subscription_start_date' => now()->addMonth()->toDateString(),
        ]),
        staffUserId: 1,
    );

    $client->refresh();
    $log = PlanChangeLog::find($result['log_id']);

    expect($log->status)->toBe('completed')
        ->and($result['new_subscription_id'])->toStartWith('DRYRUN-SUB-')
        ->and($client->payment_structure)->toBe('2')
        ->and($client->installment)->toBe('3')          // = occurrences
        ->and($client->plan_changed_at)->not->toBeNull()
        ->and(PaymentHistory::where('client_id', $client->id)->where('purpose', 'Plan change - manual charge')->exists())->toBeTrue();
});

test('applyManual refuses to create a second subscription without cancelling the first', function () {
    $client = Client::factory()->monthly()->create();
    pcProfile($client);
    PaymentHistory::create(['client_id' => $client->id, 'amount' => 30, 'payment_status' => 1, 'subscription_id' => 'SUB-OLD', 'authorize_account_id' => AuthorizeAccount::first()->id]);

    expect(fn () => app(PlanChangeService::class)->applyManual(
        $client,
        new NewPlanInput(paymentTermCode: 1, newTotalPrice: 50),
        new BillingActions([
            'create_new_subscription' => true, 'new_subscription_amount' => 50,
            'new_subscription_occurrences' => 12, 'new_subscription_start_date' => now()->addMonth()->toDateString(),
        ]),
        staffUserId: 1,
    ))->toThrow(RuntimeException::class, 'already has an active subscription');
});

test('BillingActions rejects mutually exclusive choices', function () {
    expect(fn () => new BillingActions(['update_existing_subscription' => true, 'cancel_subscription' => true]))
        ->toThrow(RuntimeException::class, 'either update or cancel');
});

test('a plain field change with no billing actions still saves the plan and logs it', function () {
    $client = Client::factory()->monthly()->create(['payment_structure' => '1', 'package' => 5]);

    $result = app(PlanChangeService::class)->applyManual(
        $client,
        new NewPlanInput(paymentTermCode: 2, newTotalPrice: 500, packageId: 9),
        new BillingActions([]),
        staffUserId: 1,
    );

    $client->refresh();
    expect($client->payment_structure)->toBe('2')
        ->and((int) $client->package)->toBe(9)
        ->and(PlanChangeLog::find($result['log_id'])->status)->toBe('completed')
        ->and($client->plan_changed_at)->toBeNull(); // no billing action -> not stamped
});
