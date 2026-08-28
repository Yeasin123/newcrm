<?php

namespace App\Services\FailedPayment\Contracts;

use App\Services\FailedPayment\FailedPaymentCommunicationResult;

interface FailedPaymentCommunicationGatewayInterface
{
    public function simulate(array $request): FailedPaymentCommunicationResult;
}
