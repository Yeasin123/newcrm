<?php

namespace App\Enums;

final class FailedPaymentAttemptStatus
{
    public const OBSERVED_FAILED = 'observed_failed';

    public const RECORDED = 'recorded';

    public const DUPLICATE_IGNORED = 'duplicate_ignored';

    public const UNMATCHED = 'unmatched';
}
