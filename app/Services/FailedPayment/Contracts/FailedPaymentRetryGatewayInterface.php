<?php

namespace App\Services\FailedPayment\Contracts;

use App\Services\FailedPayment\FailedPaymentRetryResult;

interface FailedPaymentRetryGatewayInterface
{
    public function retry(array $request): FailedPaymentRetryResult;
}
