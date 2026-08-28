<?php

namespace App\Services\FailedPayment;

use Carbon\CarbonInterface;

final class FailedPaymentRetryDecision
{
    public function __construct(
        public bool $eligible,
        public string $reasonCode,
        public string $safeReason,
        public CarbonInterface $evaluatedAt,
        public array $state = [],
    ) {}

    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'reason_code' => $this->reasonCode,
            'safe_reason' => $this->safeReason,
            'evaluated_at' => $this->evaluatedAt->toIso8601String(),
            'state' => $this->state,
        ];
    }
}
