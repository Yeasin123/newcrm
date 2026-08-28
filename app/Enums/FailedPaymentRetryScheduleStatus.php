<?php

namespace App\Enums;

final class FailedPaymentRetryScheduleStatus
{
    public const PENDING = 'pending';

    public const PAUSED = 'paused';

    public const PROCESSING = 'processing';

    public const SIMULATED = 'simulated';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public const EXHAUSTED = 'exhausted';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::PAUSED,
            self::PROCESSING,
            self::SIMULATED,
            self::COMPLETED,
            self::CANCELLED,
            self::SKIPPED,
            self::FAILED,
            self::EXHAUSTED,
        ];
    }
}
