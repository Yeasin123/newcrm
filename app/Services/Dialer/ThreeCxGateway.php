<?php

namespace App\Services\Dialer;

/**
 * Pushes a rotated outbound caller ID to the 3CX PBX for a rep's extension.
 * Bound to the Null implementation unless config('dialer.threecx_enabled').
 */
interface ThreeCxGateway
{
    /**
     * @param  string|null  $extNumber  the rep's 3CX extension
     * @param  string  $callerIdBase64  base64 of the chosen caller-ID number
     * @return mixed legacy returns the raw 3CX status / error payload
     */
    public function updateOutboundCallerId(?string $extNumber, string $callerIdBase64): mixed;
}
