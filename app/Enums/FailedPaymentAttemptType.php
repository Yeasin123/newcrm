<?php

namespace App\Enums;

final class FailedPaymentAttemptType
{
    public const WEBHOOK_FAILURE = 'webhook_failure';

    public const RECONCILIATION_FAILURE = 'reconciliation_failure';

    public const MANUAL_OBSERVATION = 'manual_observation';
}
