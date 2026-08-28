<?php

namespace App\Services\Telephony;

/** Shared phone-number helpers. Ported from the legacy TelnyxMessageService. */
abstract class AbstractSmsGateway implements SmsGateway
{
    public function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (empty($digits)) {
            return null;
        }

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    public function localPhoneVariants(?string $phone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (empty($digits)) {
            return [];
        }

        $lastTen = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return array_values(array_unique([
            (string) $phone,
            $digits,
            $lastTen,
            '+1'.$lastTen,
            '1'.$lastTen,
        ]));
    }
}
