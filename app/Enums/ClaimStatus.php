<?php

namespace App\Enums;

/**
 * Legacy `claims.status`. 0 = open, 1 = paid, 2 = closed, 3 = bypassed.
 */
enum ClaimStatus: int
{
    case Open = 0;
    case Paid = 1;
    case Closed = 2;
    case Bypassed = 3;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Paid => 'Paid',
            self::Closed => 'Closed',
            self::Bypassed => 'Bypassed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Paid => 'blue',
            self::Closed => 'green',
            self::Bypassed => 'zinc',
        };
    }

    public static function tryLabel(int|string|null $value): string
    {
        return self::tryFrom((int) $value)?->label() ?? '—';
    }
}
