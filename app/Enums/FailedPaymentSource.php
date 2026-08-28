<?php

namespace App\Enums;

final class FailedPaymentSource
{
    public const AUTHORIZE_NET_WEBHOOK = 'authorize_net_webhook';

    public const RECURRING_HEALTH = 'recurring_health';

    public const MANUAL = 'manual';
}
