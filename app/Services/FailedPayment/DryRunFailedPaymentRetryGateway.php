<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentRetryAttemptResult;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Services\FailedPayment\Contracts\FailedPaymentRetryGatewayInterface;

class DryRunFailedPaymentRetryGateway implements FailedPaymentRetryGatewayInterface
{
    public function retry(array $request): FailedPaymentRetryResult
    {
        $simulationKey = (string) ($request['simulation_key'] ?? hash('sha256', json_encode($request) ?: ''));

        return new FailedPaymentRetryResult(
            attemptResult: FailedPaymentRetryAttemptResult::DRY_RUN,
            scheduleStatus: FailedPaymentRetryScheduleStatus::SIMULATED,
            simulationKey: $simulationKey,
            gatewayReference: null,
            safeMessage: 'Dry-run retry simulated. No gateway call was made.',
            metadata: [
                'dry_run_mode' => true,
                'gateway_driver' => 'dry_run',
            ],
        );
    }
}
