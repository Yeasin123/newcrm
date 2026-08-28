<?php

use App\Models\Addon;
use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Models\Package;
use App\Models\PaymentHistory;
use App\Models\PropertySize;
use App\Services\Billing\PaymentInput;
use App\Services\SaleSheet\SaleSheetPriceCalculator;
use App\Services\SaleSheet\SaleSheetService;
use Illuminate\Support\Facades\Http;

function saleMerchant(): AuthorizeAccount
{
    return AuthorizeAccount::create([
        'name' => 'Primary', 'environment' => 'sandbox', 'api_login_id' => 'l', 'transaction_key' => 'k',
        'is_active' => true, 'is_default_charge' => true, 'is_default_client' => true, 'weight' => 100,
    ]);
}

function salePlan(float $monthly = 50, float $yearly = 500): Package
{
    return Package::create(['title' => 'Platinum', 'monthly' => (string) $monthly, 'yearly' => (string) $yearly]);
}

test('monthly term is the flat catalog price', function () {
    $plan = salePlan(monthly: 54.99);

    $price = app(SaleSheetPriceCalculator::class)->calculate(term: 1, packageId: $plan->id);

    expect($price->packagePrice)->toBe(54.99)
        ->and($price->payable)->toBe(54.99)
        ->and($price->recurring)->toBeTrue();
});

test('multi-year multiplier is term minus one', function () {
    $plan = salePlan(yearly: 500);

    expect(app(SaleSheetPriceCalculator::class)->calculate(term: 3, packageId: $plan->id)->payable)->toBe(1000.0) // 2yr x2
        ->and(app(SaleSheetPriceCalculator::class)->calculate(term: 4, packageId: $plan->id)->payable)->toBe(1500.0) // 3yr x3
        ->and(app(SaleSheetPriceCalculator::class)->calculate(term: 6, packageId: $plan->id)->payable)->toBe(2500.0); // 5yr x5
});

test('a percentage-currency property size is a share of the package price', function () {
    $plan = salePlan(monthly: 100);
    $size = PropertySize::create(['title' => 'Large', 'monthly' => '10', 'yearly' => '10', 'currency' => '%']);

    $price = app(SaleSheetPriceCalculator::class)->calculate(term: 1, packageId: $plan->id, propertySizeId: $size->id);

    expect($price->propertySizePrice)->toBe(10.0) // 10% of 100
        ->and($price->subtotal)->toBe(110.0);
});

test('a percentage discount is applied once to the pre-discount total', function () {
    $plan = salePlan(monthly: 200);

    $price = app(SaleSheetPriceCalculator::class)->calculate(term: 1, packageId: $plan->id, discountPercent: 25);

    expect($price->discount)->toBe(50.0)
        ->and($price->payable)->toBe(150.0);
});

test('an installment split divides the total into equal payments', function () {
    $plan = salePlan(yearly: 1200);

    $price = app(SaleSheetPriceCalculator::class)->calculate(term: 2, packageId: $plan->id, installmentSplit: 4);

    expect($price->isInstallment)->toBeTrue()
        ->and($price->payable)->toBe(300.0) // 1200 / 4
        ->and($price->subtotal)->toBe(1200.0);
});

test('a single-property monthly sale creates one active client and charges once', function () {
    Http::preventStrayRequests();
    saleMerchant();
    $plan = salePlan(monthly: 54.99);
    $addon = Addon::create(['title' => 'Pool', 'monthly' => '10', 'yearly' => '100']);

    $result = app(SaleSheetService::class)->place(
        client: ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'contact' => '8887777777', 'salesman_name' => null],
        properties: [[
            'address' => '123 Main St', 'city' => 'LA', 'state' => 'CA', 'zip_code' => '90210',
            'package' => $plan->id, 'addons' => [$addon->id], 'payment_term' => 1,
        ]],
        payment: new PaymentInput(opaqueDataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT', opaqueDataValue: 'DRYRUN-tok', explicitLast4: '4242'),
        bypassPayment: false,
    );

    expect($result['clients'])->toHaveCount(1);
    $client = $result['primary'];
    expect((int) $client->status)->toBe(1)
        ->and($client->multi_property)->toBeNull()
        ->and($client->payment_structure)->toBe('1')
        ->and($client->services)->toBe(json_encode([$addon->id]));

    expect((float) $result['history']->amount)->toBe(64.99) // 54.99 + 10
        ->and($result['history']->subscription_id)->toStartWith('DRYRUN-SUB-');
});

test('a two-property sale shares a multi_property key and takes one combined charge', function () {
    Http::preventStrayRequests();
    saleMerchant();
    $plan = salePlan(monthly: 40);

    $result = app(SaleSheetService::class)->place(
        client: ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'contact' => '8887777777'],
        properties: [
            ['address' => '1 A St', 'city' => 'LA', 'state' => 'CA', 'zip_code' => '90001', 'package' => $plan->id, 'payment_term' => 1],
            ['address' => '2 B St', 'city' => 'LA', 'state' => 'CA', 'zip_code' => '90002', 'package' => $plan->id, 'payment_term' => 1],
        ],
        payment: new PaymentInput(opaqueDataDescriptor: 'COMMON.ACCEPT.INAPP.PAYMENT', opaqueDataValue: 'DRYRUN-tok', explicitLast4: '4242'),
        bypassPayment: false,
    );

    $keys = $result['clients']->pluck('multi_property')->unique();
    expect($keys)->toHaveCount(1)
        ->and($keys->first())->not->toBeNull()
        ->and($result['clients']->every(fn ($c) => (int) $c->status === 1))->toBeTrue();

    expect(PaymentHistory::count())->toBe(1)
        ->and((float) $result['history']->amount)->toBe(80.0); // 40 + 40
});

test('bypass payment activates every row without charging', function () {
    Http::preventStrayRequests();
    saleMerchant();
    $plan = salePlan(monthly: 40);

    $result = app(SaleSheetService::class)->place(
        client: ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'contact' => '8887777777'],
        properties: [['address' => '9 Z St', 'city' => 'LA', 'state' => 'CA', 'zip_code' => '90009', 'package' => $plan->id, 'payment_term' => 1]],
        payment: new PaymentInput,
        bypassPayment: true,
    );

    expect((int) $result['primary']->status)->toBe(5)
        ->and((int) $result['primary']->bypass_payment)->toBe(1)
        ->and($result['history'])->toBeNull()
        ->and(PaymentHistory::count())->toBe(0)
        ->and(Client::count())->toBe(1);
});
