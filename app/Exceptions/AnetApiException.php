<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an Authorize.Net API call returns a non-"Ok" result (or an
 * unusable response). Carries the gateway's own error code + text.
 */
class AnetApiException extends RuntimeException
{
    public function __construct(
        public readonly string $anetCode,
        public readonly string $anetText,
        public readonly array $raw = [],
    ) {
        parent::__construct("Authorize.Net error [{$anetCode}]: {$anetText}");
    }
}
