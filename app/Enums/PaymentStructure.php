<?php

namespace App\Enums;

/**
 * Legacy `clients.payment_structure` — a varchar column holding '1' or '2'.
 */
enum PaymentStructure: string
{
    case Monthly = '1';
    case Yearly = '2';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    public static function tryLabel(?string $value): string
    {
        return self::tryFrom((string) $value)?->label() ?? '—';
    }
}
