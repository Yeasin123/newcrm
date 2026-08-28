<?php

namespace App\Services\FailedPayment;

final class FailedPaymentCommunicationResult
{
    public function __construct(
        public string $status,
        public string $channel,
        public string $templateId,
        public int $templateVersion,
        public ?string $subject,
        public string $message,
        public ?string $recipient,
        public ?string $maskedRecipient,
        public ?string $simulationKey = null,
        public ?string $providerReference = null,
        public ?int $communicationId = null,
        public ?string $failureReason = null,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'channel' => $this->channel,
            'template_id' => $this->templateId,
            'template_version' => $this->templateVersion,
            'subject' => $this->subject,
            'message' => $this->message,
            'recipient' => $this->recipient,
            'masked_recipient' => $this->maskedRecipient,
            'simulation_key' => $this->simulationKey,
            'provider_reference' => $this->providerReference,
            'communication_id' => $this->communicationId,
            'failure_reason' => $this->failureReason,
            'metadata' => $this->metadata,
        ];
    }
}
