<?php

namespace App\Services\FailedPayment;

use App\Enums\FailedPaymentRetryAttemptResult;
use App\Enums\FailedPaymentRetryScheduleStatus;
use App\Services\FailedPayment\Contracts\FailedPaymentRetryGatewayInterface;

class UnavailableFailedPaymentRetryGateway implements FailedPaymentRetryGatewayInterface
{
    public function retry(array $request): FailedPaymentRetryResult
    {
        return new FailedPaymentRetryResult(
            attemptResult: FailedPaymentRetryAttemptResult::BLOCKED,
            scheduleStatus: FailedPaymentRetryScheduleStatus::PENDING,
            simulationKey: null,
            gatewayReference: null,
            safeMessage: 'Live retry gateway is unavailable or not configured.',
            metadata: [
                'gateway_driver' => 'unavailable',
                'dry_run_mode' => false,
            ],
        );
    }
}
