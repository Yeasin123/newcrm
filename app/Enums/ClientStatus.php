<?php

namespace App\Enums;

/**
 * Legacy `clients.status` — 1 = active / good standing, 0 = suspended.
 */
enum ClientStatus: int
{
    case Suspended = 0;
    case Active = 1;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    /** Flux badge colour for this status. */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Suspended => 'red',
        };
    }
}
