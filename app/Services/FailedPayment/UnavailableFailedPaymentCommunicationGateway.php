<?php

namespace App\Services\FailedPayment;

use App\Services\FailedPayment\Contracts\FailedPaymentCommunicationGatewayInterface;

class UnavailableFailedPaymentCommunicationGateway implements FailedPaymentCommunicationGatewayInterface
{
    public function simulate(array $request): FailedPaymentCommunicationResult
    {
        return new FailedPaymentCommunicationResult(
            status: 'blocked',
            channel: (string) ($request['channel'] ?? 'email'),
            templateId: (string) ($request['template_id'] ?? ''),
            templateVersion: (int) ($request['template_version'] ?? 1),
            subject: null,
            message: 'Communication simulation is disabled.',
            recipient: null,
            maskedRecipient: null,
            simulationKey: isset($request['simulation_key']) ? (string) $request['simulation_key'] : null,
            providerReference: null,
            metadata: [
                'gateway_driver' => 'unavailable',
                'delivery_mode' => 'blocked',
            ],
        );
    }
}
