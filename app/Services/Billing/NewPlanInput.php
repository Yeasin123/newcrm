<?php

namespace App\Services\Billing;

/**
 * The proposed new plan for a plan change. `newTotalPrice` is the discounted
 * plan total — supplied by the caller (staff-entered on the Edit Plan panel,
 * or computed by the pricing engine once Phase 12 lands). `customPrice` /
 * `discount` still override it here exactly as the legacy code did.
 *
 * paymentTermCode: 1 = monthly, 2..5 = fixed-term years (15/29/43/73 months).
 */
class NewPlanInput
{
    /** @param array<int,int|string> $addonIds */
    public function __construct(
        public readonly int $paymentTermCode,
        public readonly bool $installmentOn = false,
        public readonly int $installmentType = 0,
        public readonly float $newTotalPrice = 0.0,
        public readonly ?int $packageId = null,
        public readonly ?int $propertySize = null,
        public readonly ?int $propertyType = null,
        public readonly array $addonIds = [],
        public readonly string $discount = '0',
        public readonly float $discountValue = 0.0,
        public readonly ?string $customPrice = null,
        public readonly ?string $serviceCallFee = null,
        public readonly ?string $salesmanName = null,
        public readonly ?string $clientNotes = null,
        public readonly ?string $coverageStartDate = null,
        public readonly ?string $customPurchaseDate = null,
    ) {}

    public function paymentStructure(): int
    {
        return $this->paymentTermCode === 1 ? 1 : 2;
    }

    /** Fixed-term length in months for a yearly term code. */
    public function termMonths(): int
    {
        return $this->paymentStructure() === 1 ? 0 : match ($this->paymentTermCode) {
            2 => 15, 3 => 29, 4 => 43, 5 => 73, default => 15,
        };
    }

    /** The effective new price after any custom-price + discount override. */
    public function effectivePrice(): float
    {
        if (! filled($this->customPrice)) {
            return round($this->newTotalPrice, 2);
        }

        $custom = (float) preg_replace('/[^0-9.]/', '', (string) $this->customPrice);

        return ($this->discount === '1' && $this->discountValue > 0)
            ? round($custom - (($this->discountValue / 100) * $custom), 2)
            : round($custom, 2);
    }
}
