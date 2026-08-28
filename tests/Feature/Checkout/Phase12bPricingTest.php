<?php

use App\Models\Addon;
use App\Models\CustomPlanPrice;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Services\Checkout\CheckoutPriceCalculator;
use App\Services\Checkout\PromoCodeService;

function seedPlan(float $monthly = 50, float $yearly = 500): Package
{
    $package = Package::create(['title' => 'Total Home', 'monthly' => (string) $monthly, 'yearly' => (string) $yearly]);
    CustomPlanPrice::create([
        'package_id' => $package->id,
        'original_monthly' => $monthly,
        'original_yearly' => $yearly,
        'new_monthly' => $monthly,
        'new_yearly' => $yearly,
    ]);

    return $package;
}

test('monthly term uses the custom monthly price with no multiplier', function () {
    $package = seedPlan(monthly: 49.99);

    $breakdown = app(CheckoutPriceCalculator::class)->calculate(term: 1, packageId: $package->id);

    expect($breakdown->packagePrice)->toBe(49.99)
        ->and($breakdown->subtotal)->toBe(49.99)
        ->and($breakdown->payable)->toBe(49.99)
        ->and($breakdown->recurring)->toBeTrue()
        ->and($breakdown->priceField)->toBe('monthly');
});

test('the 3-year term multiplies the yearly price by three', function () {
    $package = seedPlan(yearly: 500);

    $breakdown = app(CheckoutPriceCalculator::class)->calculate(term: 4, packageId: $package->id);

    expect($breakdown->multiplier)->toBe(3)
        ->and($breakdown->packagePrice)->toBe(1500.0)
        ->and($breakdown->recurring)->toBeFalse();
});

test('add-ons are added at the term price times the multiplier', function () {
    $package = seedPlan(yearly: 500);
    $a = Addon::create(['title' => 'Pool', 'monthly' => '10', 'yearly' => '100']);
    $b = Addon::create(['title' => 'Roof', 'monthly' => '5', 'yearly' => '60']);

    $breakdown = app(CheckoutPriceCalculator::class)->calculate(term: 2, packageId: $package->id, addonIds: [$a->id, $b->id]);

    expect($breakdown->addonPrice)->toBe(160.0)
        ->and($breakdown->subtotal)->toBe(660.0);
});

test('a fixed discount is subtracted once and floored at zero', function () {
    $package = seedPlan(monthly: 40);

    $breakdown = app(CheckoutPriceCalculator::class)->calculate(
        term: 1, packageId: $package->id, discountValue: 15, discountType: config('checkout.discount_type.fixed'),
    );

    expect($breakdown->discount)->toBe(15.0)
        ->and($breakdown->payable)->toBe(25.0)
        ->and($breakdown->isDiscounted())->toBeTrue();

    $huge = app(CheckoutPriceCalculator::class)->calculate(
        term: 1, packageId: $package->id, discountValue: 999, discountType: config('checkout.discount_type.fixed'),
    );
    expect($huge->payable)->toBe(0.0);
});

test('a percentage discount is a share of the pre-discount subtotal', function () {
    $package = seedPlan(yearly: 200);

    $breakdown = app(CheckoutPriceCalculator::class)->calculate(
        term: 2, packageId: $package->id, discountValue: 25, discountType: config('checkout.discount_type.percent'),
    );

    expect($breakdown->discount)->toBe(50.0)
        ->and($breakdown->payable)->toBe(150.0);
});

test('promo lookup is case-insensitive and rejects unknown codes', function () {
    Promotion::create(['name' => 'save20', 'amount' => 20, 'promotion_type' => 2]);
    $svc = app(PromoCodeService::class);

    $hit = $svc->apply('SAVE20');
    expect($hit->valid)->toBeTrue()
        ->and($hit->discountValue())->toBe(20.0)
        ->and($hit->discountType())->toBe(2);

    expect($svc->apply('nope')->valid)->toBeFalse();
});

test('autoApply requires the code to be conditioned onto the selected package', function () {
    $package = seedPlan();
    $other = seedPlan();
    Promotion::create(['name' => 'pkgonly', 'amount' => 10, 'promotion_type' => 1]);
    PromotionCondition::create([
        'promotion_id' => 1, 'name' => 'pkgonly', 'package_id' => json_encode([(string) $package->id]),
    ]);
    $svc = app(PromoCodeService::class);

    expect($svc->autoApply('pkgonly', $package->id)->valid)->toBeTrue()
        ->and($svc->autoApply('pkgonly', $other->id)->valid)->toBeFalse();
});
