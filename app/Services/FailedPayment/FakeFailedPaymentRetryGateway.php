<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentRetryAttemptResult;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Services\FailedPayment\Contracts\FailedPaymentRetryGatewayInterface;

class FakeFailedPaymentRetryGateway implements FailedPaymentRetryGatewayInterface
{
    public function __construct(
        protected ?FailedPaymentRetryResult $response = null
    ) {}

    public function retry(array $request): FailedPaymentRetryResult
    {
        if ($this->response) {
            return $this->response;
        }

        $simulationKey = (string) ($request['simulation_key'] ?? hash('sha256', json_encode($request) ?: ''));

        return new FailedPaymentRetryResult(
            attemptResult: FailedPaymentRetryAttemptResult::DRY_RUN,
            scheduleStatus: FailedPaymentRetryScheduleStatus::SIMULATED,
            simulationKey: $simulationKey,
            gatewayReference: 'fake-dry-run',
            safeMessage: 'Fake retry gateway defaulted to dry-run.',
            metadata: [
                'gateway_driver' => 'fake',
                'dry_run_mode' => true,
            ],
        );
    }
}
