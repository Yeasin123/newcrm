<?php

namespace App\Services\SaleSheet;

/**
 * One property's price, as computed by SaleSheetPriceCalculator. Mirrors the
 * fields Admin\ClientsController::priceCalculator returned.
 */
readonly class SalePrice
{
    /**
     * @param  list<string>  $packageServiceTitles
     */
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
        public bool $isInstallment,
        public ?int $installmentSplit,
        public string $priceField,
        public int $multiplier,
        public array $packageServiceTitles = [],
    ) {}

    public function isDiscounted(): bool
    {
        return $this->discount > 0;
    }

    /** @return array<string, mixed> Legacy-shaped payload stored on clients.price_cal_response. */
    public function toLegacyArray(): array
    {
        return [
            'status' => 200,
            'payment_term' => number_format($this->payable, 2),
            'price' => number_format($this->payable, 2),
            'discount_price' => number_format($this->isDiscounted() ? $this->payable : 0, 2),
            'installment_price' => number_format($this->isInstallment ? $this->payable : 0, 2),
            'property_size' => $this->propertySizePrice,
            'property_type' => $this->propertyTypePrice,
            'package' => $this->packagePrice,
            'addonPrice' => $this->addonPrice,
            'discount' => $this->discount,
            'is_installment' => $this->isInstallment ? 1 : 0,
            'total_installment' => $this->installmentSplit,
        ];
    }
}
