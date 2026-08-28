<?php

namespace App\Services\Checkout;

use App\Models\Addon;
use App\Models\CustomPlanPrice;
use App\Models\Package;
use App\Models\PropertySize;
use App\Models\PropertyType;

/**
 * Faithful port of PlanCheckoutController::priceCalculator (the newest of the
 * three legacy checkout controllers). Business rules kept intact:
 *
 *   - term selects the price column (1 => monthly, everything else => yearly)
 *     and a year multiplier (1yr x1, 3yr x3, 5yr x5) — see config('checkout.terms');
 *   - the package price comes from `custom_plan_prices` keyed by the package id
 *     (legacy: CustomPlanPrice::find($package->id)->original_{monthly,yearly}),
 *     NOT from the `packages` catalog columns;
 *   - property-size / property-type / add-on prices are their per-term column
 *     value times the multiplier;
 *   - the discount is applied exactly once to the pre-discount subtotal:
 *     type 1 = a fixed dollar amount, type 2 = a percentage; the result is
 *     floored at zero.
 */
class CheckoutPriceCalculator
{
    /**
     * @param  array<int, int|string>  $addonIds
     */
    public function calculate(
        int $term,
        int $packageId,
        array $addonIds = [],
        float $discountValue = 0.0,
        ?int $discountType = null,
        ?int $propertySizeId = null,
        ?int $propertyTypeId = null,
    ): PriceBreakdown {
        $terms = config('checkout.terms');
        $termCfg = $terms[$term] ?? $terms[1];
        $field = $termCfg['field'];
        $multiplier = (int) $termCfg['multiplier'];

        $propertySizePrice = $this->catalogPrice(PropertySize::class, $propertySizeId, $field, $multiplier);
        $propertyTypePrice = $this->catalogPrice(PropertyType::class, $propertyTypeId, $field, $multiplier);

        $addonPrice = 0.0;
        foreach ($addonIds as $addonId) {
            $addon = Addon::find($addonId);
            if ($addon && isset($addon->{$field})) {
                $addonPrice += (float) $addon->{$field} * $multiplier;
            }
        }

        $packagePrice = 0.0;
        if ($packageId && Package::find($packageId)) {
            $custom = CustomPlanPrice::find($packageId);
            $base = $term === 1
                ? (float) ($custom->original_monthly ?? 0)
                : (float) ($custom->original_yearly ?? 0);
            $packagePrice = $base * $multiplier;
        }

        $subtotal = $propertySizePrice + $propertyTypePrice + $packagePrice + $addonPrice;

        $discount = 0.0;
        $payable = $subtotal;
        if ($discountValue > 0) {
            $percent = config('checkout.discount_type.percent');
            $magnitude = (int) $discountType === $percent
                ? ($discountValue / 100) * $subtotal
                : $discountValue;

            $payable = max($subtotal - $magnitude, 0);
            $discount = $subtotal - $payable;
        }

        return new PriceBreakdown(
            term: $term,
            packagePrice: round($packagePrice, 2),
            addonPrice: round($addonPrice, 2),
            propertySizePrice: round($propertySizePrice, 2),
            propertyTypePrice: round($propertyTypePrice, 2),
            subtotal: round($subtotal, 2),
            discount: round($discount, 2),
            payable: round($payable, 2),
            recurring: (bool) $termCfg['recurring'],
            priceField: $field,
            multiplier: $multiplier,
        );
    }

    /**
     * @param  class-string<PropertySize|PropertyType>  $model
     */
    private function catalogPrice(string $model, ?int $id, string $field, int $multiplier): float
    {
        if (! $id) {
            return 0.0;
        }

        $row = $model::find($id);

        return $row && isset($row->{$field})
            ? (float) $row->{$field} * $multiplier
            : 0.0;
    }
}
