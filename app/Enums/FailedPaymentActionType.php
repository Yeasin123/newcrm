<?php

namespace App\Enums;

final class FailedPaymentActionType
{
    public const SYSTEM_EVENT = 'system_event';

    public const NOTE = 'note';

    public const TASK = 'task';

    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const CALL = 'call';

    public const VOICEMAIL = 'voicemail';
}
