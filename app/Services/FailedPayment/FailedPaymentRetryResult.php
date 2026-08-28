<?php

namespace App\Services\FailedPayment;

final class FailedPaymentRetryResult
{
    public function __construct(
        public string $attemptResult,
        public string $scheduleStatus,
        public ?string $simulationKey = null,
        public ?string $gatewayReference = null,
        public ?string $safeMessage = null,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'attempt_result' => $this->attemptResult,
            'schedule_status' => $this->scheduleStatus,
            'simulation_key' => $this->simulationKey,
            'gateway_reference' => $this->gatewayReference,
            'safe_message' => $this->safeMessage,
            'metadata' => $this->metadata,
        ];
    }
}
