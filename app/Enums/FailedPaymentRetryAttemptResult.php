<?php

namespace App\Enums;

final class FailedPaymentRetryAttemptResult
{
    public const DRY_RUN = 'dry_run';

    public const SUCCESS = 'success';

    public const DECLINED = 'declined';

    public const TEMPORARY_FAILURE = 'temporary_failure';

    public const PERMANENT_FAILURE = 'permanent_failure';

    public const BLOCKED = 'blocked';

    public const EXCEPTION = 'exception';

    public static function all(): array
    {
        return [
            self::DRY_RUN,
            self::SUCCESS,
            self::DECLINED,
            self::TEMPORARY_FAILURE,
            self::PERMANENT_FAILURE,
            self::BLOCKED,
            self::EXCEPTION,
        ];
    }
}
