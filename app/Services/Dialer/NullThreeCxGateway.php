<?php

namespace App\Services\Dialer;

/** No-op — the default. Caller-ID rotation still happens locally. */
class NullThreeCxGateway implements ThreeCxGateway
{
    public function updateOutboundCallerId(?string $extNumber, string $callerIdBase64): mixed
    {
        return ['status' => 'skipped', 'reason' => '3cx_disabled'];
    }
}
