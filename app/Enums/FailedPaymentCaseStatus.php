<?php

namespace App\Enums;

final class FailedPaymentCaseStatus
{
    public const NEW = 'new';

    public const OPEN = 'open';

    public const UNMATCHED = 'unmatched';

    public const UNDER_REVIEW = 'under_review';

    public const RECOVERED = 'recovered';

    public const RESOLVED = 'resolved';

    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::NEW,
            self::OPEN,
            self::UNMATCHED,
            self::UNDER_REVIEW,
            self::RECOVERED,
            self::RESOLVED,
            self::CANCELLED,
        ];
    }
}
