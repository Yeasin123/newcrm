<?php

namespace App\Services\Checkout;

use App\Models\Promotion;
use App\Models\PromotionCondition;

/**
 * Promo-code lookup. Port of Admin\PromotionController::applyPromoCode
 * (name match only) and ::autoApplyPromoCode (name match AND the code must
 * be conditioned onto the selected package). The legacy JSON_CONTAINS check
 * is done in PHP here so it works on both MySQL and the test sqlite.
 *
 * Codes are matched case-insensitively on `promotions.name` (the legacy code
 * lower-cases the input before querying).
 */
class PromoCodeService
{
    public function apply(string $code): PromoResult
    {
        $promotion = $this->lookup($code);

        return $promotion
            ? PromoResult::valid($promotion)
            : PromoResult::invalid();
    }

    public function autoApply(string $code, int|string|null $packageId): PromoResult
    {
        $promotion = $this->lookup($code);

        if (! $promotion || $packageId === null) {
            return PromoResult::invalid();
        }

        $conditioned = PromotionCondition::where('name', $promotion->name)
            ->get()
            ->contains(fn (PromotionCondition $c) => in_array((string) $packageId, $c->packageIds(), true));

        return $conditioned
            ? PromoResult::valid($promotion)
            : PromoResult::invalid();
    }

    private function lookup(string $code): ?Promotion
    {
        $code = strtolower(trim($code));

        if ($code === '') {
            return null;
        }

        return Promotion::whereRaw('LOWER(name) = ?', [$code])->first();
    }
}
