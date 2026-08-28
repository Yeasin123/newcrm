<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when the Vanilasoft dialer requests a caller ID for an outbound call.
 * Ported from the legacy `VanilasofEvent`; no listener yet — kept as an
 * extension point.
 *
 * @param  array{contact:mixed, userid:mixed}  $payload
 */
class VanilasoftDialCall
{
    use Dispatchable;

    public function __construct(public readonly array $payload) {}
}
