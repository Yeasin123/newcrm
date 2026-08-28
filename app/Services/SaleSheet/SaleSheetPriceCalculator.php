<?php

namespace App\Services\SaleSheet;

use App\Models\Addon;
use App\Models\Package;
use App\Models\PropertySize;
use App\Models\PropertyType;

/**
 * Faithful port of Admin\ClientsController::priceCalculator — the pricing
 * behind the staff "Add Customer" sale sheet. Business rules kept intact:
 *
 *   - term 1 = monthly (x1); terms 2/3/4/6 = yearly with multiplier
 *     `term - 1` (1yr x1, 2yr x2, 3yr x3, 5yr x5) — see config('sale_sheet.terms');
 *   - package / add-on / property prices are the per-term catalog column
 *     value times the multiplier (package from the `packages` table, NOT
 *     `custom_plan_prices`);
 *   - a property-size or property-type row whose `currency` is '%' is a
 *     percentage of the package price instead of a flat amount;
 *   - the discount is percentage-only, applied once to the pre-discount total;
 *   - an installment split (2..5) divides the (discounted) total into that
 *     many equal payments — `payable` is then one payment.
 */
class SaleSheetPriceCalculator
{
    /**
     * @param  array<int, int|string>  $addonIds
     */
    public function calculate(
        int $term,
        ?int $packageId,
        array $addonIds = [],
        ?int $propertySizeId = null,
        ?int $propertyTypeId = null,
        float $discountPercent = 0.0,
        ?int $installmentSplit = null,
    ): SalePrice {
        $terms = config('sale_sheet.terms');
        $cfg = $terms[$term] ?? $terms[1];
        $field = $cfg['field'];
        $multiplier = (int) $cfg['multiplier'];

        $package = 0.0;
        $serviceTitles = [];
        if ($packageId && $pkg = Package::find($packageId)) {
            $package = (float) $pkg->{$field} * $multiplier;
            $serviceTitles = $this->packageServiceTitles($pkg);
        }

        $addonPrice = 0.0;
        foreach ($addonIds as $addonId) {
            if ($addon = Addon::find($addonId)) {
                $addonPrice += (float) $addon->{$field} * $multiplier;
            }
        }

        $sizePrice = $this->catalogPrice(PropertySize::find($propertySizeId), $field, $multiplier, $package);
        $typePrice = $this->catalogPrice(PropertyType::find($propertyTypeId), $field, $multiplier, $package);

        $base = $sizePrice + $typePrice + $package + $addonPrice;

        $discountValue = 0.0;
        $afterDiscount = $base;
        if ($discountPercent > 0) {
            $discountValue = $discountPercent / 100 * $base;
            $afterDiscount = $base - $discountValue;
        }

        $isInstallment = $installmentSplit !== null
            && in_array($installmentSplit, config('sale_sheet.installment_splits'), true);

        $payable = $isInstallment
            ? $afterDiscount / $installmentSplit
            : $afterDiscount;

        return new SalePrice(
            term: $term,
            packagePrice: round($package, 2),
            addonPrice: round($addonPrice, 2),
            propertySizePrice: round($sizePrice, 2),
            propertyTypePrice: round($typePrice, 2),
            subtotal: round($base, 2),
            discount: round($discountValue, 2),
            payable: round($payable, 2),
            recurring: (bool) $cfg['recurring'],
            isInstallment: $isInstallment,
            installmentSplit: $isInstallment ? $installmentSplit : null,
            priceField: $field,
            multiplier: $multiplier,
            packageServiceTitles: $serviceTitles,
        );
    }

    /** @return list<string> */
    private function packageServiceTitles(Package $pkg): array
    {
        try {
            $titles = $pkg->services()->pluck('title')->all();

            return array_map('strval', $titles);
        } catch (\Throwable) {
            return [];
        }
    }

    private function catalogPrice(PropertySize|PropertyType|null $row, string $field, int $multiplier, float $package): float
    {
        if (! $row || ! isset($row->{$field})) {
            return 0.0;
        }

        if (($row->currency ?? null) === '%') {
            return (float) $row->{$field} / 100 * $package;
        }

        return (float) $row->{$field} * $multiplier;
    }
}
