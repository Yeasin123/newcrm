<?php

namespace App\Services\Telephony;

/**
 * Outbound SMS. Bound to NullSmsGateway unless config('telephony.sms_enabled').
 * The phone helpers are pure and always available regardless of the binding.
 */
interface SmsGateway
{
    /**
     * @return array{success:bool, message_id?:?string, payload?:mixed, error?:?string}
     */
    public function send(string $to, string $body, ?string $from = null): array;

    /** E.164 (`+1XXXXXXXXXX`) or null when the input has no digits. */
    public function normalizePhone(?string $phone): ?string;

    /**
     * The set of strings a stored `users.contact` might take for one number —
     * used to match an inbound webhook's `from` back to a salesman.
     *
     * @return array<int,string>
     */
    public function localPhoneVariants(?string $phone): array;
}
