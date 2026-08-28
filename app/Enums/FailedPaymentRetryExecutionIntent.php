<?php

namespace App\Enums;

final class FailedPaymentRetryExecutionIntent
{
    public const SIMULATION = 'simulation';

    public const AUTOMATIC = 'automatic';

    public const MANUAL = 'manual';

    public static function all(): array
    {
        return [
            self::SIMULATION,
            self::AUTOMATIC,
            self::MANUAL,
        ];
    }

    public static function normalize(?string $intent): ?string
    {
        $intent = strtolower(trim((string) $intent));
        $intent = str_replace(['-', ' '], '_', $intent);

        return in_array($intent, self::all(), true) ? $intent : null;
    }
}
