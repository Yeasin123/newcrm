<?php

namespace App\Services\Checkout;

/**
 * The outcome of CheckoutPriceCalculator::calculate(). Mirrors the fields the
 * legacy PlanCheckoutController::priceCalculator returned, plus a resolved
 * `payable` (what the customer is actually charged today) so callers do not
 * repeat the "discount_price when > 0 else price" dance.
 */
readonly class PriceBreakdown
{
    public function __construct(
        public int $term,
        public float $packagePrice,
        public float $addonPrice,
        public float $propertySizePrice,
        public float $propertyTypePrice,
        public float $subtotal,
        public float $discount,
        public float $payable,
        public bool $recurring,
        public string $priceField,
        public int $multiplier,
    ) {}

    public function isDiscounted(): bool
    {
        return $this->discount > 0;
    }

    /** @return array<string, mixed> Legacy-shaped payload for any view/JS that still wants it. */
    public function toLegacyArray(): array
    {
        return [
            'status' => 200,
            'price' => number_format($this->subtotal, 2),
            'discount_price' => number_format($this->isDiscounted() ? $this->payable : 0, 2),
            'installment_price' => number_format(0, 2),
            'property_size' => $this->propertySizePrice,
            'property_type' => $this->propertyTypePrice,
            'package' => number_format($this->packagePrice, 2),
            'addonPrice' => number_format($this->addonPrice, 2),
            'message' => 'Successfully calculated.',
        ];
    }
}
