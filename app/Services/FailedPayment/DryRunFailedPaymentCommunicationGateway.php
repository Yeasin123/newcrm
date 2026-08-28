<?php

namespace App\Services\FailedPayment;

use App\Services\FailedPayment\Contracts\FailedPaymentCommunicationGatewayInterface;

class DryRunFailedPaymentCommunicationGateway implements FailedPaymentCommunicationGatewayInterface
{
    public function simulate(array $request): FailedPaymentCommunicationResult
    {
        return new FailedPaymentCommunicationResult(
            status: 'simulated',
            channel: (string) ($request['channel'] ?? 'email'),
            templateId: (string) ($request['template_id'] ?? ''),
            templateVersion: (int) ($request['template_version'] ?? 1),
            subject: isset($request['subject']) ? (string) $request['subject'] : null,
            message: (string) ($request['message'] ?? ''),
            recipient: isset($request['recipient']) ? (string) $request['recipient'] : null,
            maskedRecipient: isset($request['masked_recipient']) ? (string) $request['masked_recipient'] : null,
            simulationKey: isset($request['simulation_key']) ? (string) $request['simulation_key'] : null,
            providerReference: isset($request['provider_reference']) ? (string) $request['provider_reference'] : null,
            metadata: array_merge([
                'gateway_driver' => 'dry_run',
                'delivery_mode' => 'no_delivery',
            ], (array) ($request['metadata'] ?? [])),
        );
    }
}
