<?php

namespace App\Support;

/** Card-brand detection from a PAN. Ported from the legacy AnetCimService::detectBrand. */
class CardBrand
{
    public static function detect(?string $cardNumber): ?string
    {
        if (! $cardNumber) {
            return null;
        }

        $n = preg_replace('/\D+/', '', $cardNumber);

        return match (true) {
            (bool) preg_match('/^4\d{12}(\d{3})?(\d{3})?$/', $n) => 'Visa',
            (bool) preg_match('/^(5[1-5]\d{14}|2(2[2-9]\d{12}|[3-6]\d{13}|7[01]\d{12}|720\d{12}))$/', $n) => 'MasterCard',
            (bool) preg_match('/^3[47]\d{13}$/', $n) => 'Amex',
            (bool) preg_match('/^6(?:011|5\d{2})\d{12}$/', $n) => 'Discover',
            default => 'Card',
        };
    }
}
