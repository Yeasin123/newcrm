<?php

namespace App\Services\Checkout;

use App\Models\Promotion;

readonly class PromoResult
{
    private function __construct(
        public bool $valid,
        public ?Promotion $promotion,
        public string $message,
    ) {}

    public static function valid(Promotion $promotion): self
    {
        return new self(true, $promotion, 'Promo code applied.');
    }

    public static function invalid(): self
    {
        return new self(false, null, 'Invalid promo code');
    }

    public function discountValue(): float
    {
        return $this->promotion ? (float) $this->promotion->amount : 0.0;
    }

    public function discountType(): ?int
    {
        return $this->promotion ? (int) $this->promotion->promotion_type : null;
    }
}
