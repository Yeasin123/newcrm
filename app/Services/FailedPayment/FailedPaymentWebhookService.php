<?php

namespace App\Services\FailedPayment;

use App\Models\FailedPaymentAuditLog;
use App\Models\FailedPaymentWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FailedPaymentWebhookService
{
    public function recordReceived(array $context): ?FailedPaymentWebhookEvent
    {
        $context = $this->normalizeContext($context);

        try {
            return DB::transaction(function () use ($context) {
                $payloadHash = $this->payloadHash($this->duplicateDetectionPayload($context['payload']));
                $provider = $context['provider'];
                $eventId = $context['event_id'];

                $existing = $this->findExistingEvent($provider, $eventId, $payloadHash);
                if ($existing) {
                    $this->recordAuditLog($existing, 'webhook_duplicate', $context, [
                        'event_id' => $eventId,
                        'payload_hash' => $payloadHash,
                    ]);

                    return $existing;
                }

                $event = FailedPaymentWebhookEvent::create([
                    'provider' => $provider,
                    'event_id' => $eventId,
                    'event_type' => $context['event_type'],
                    'payload_hash' => $payloadHash,
                    'sanitized_payload' => $this->sanitizePayload($context['payload']),
                    'signature_validation_status' => $context['signature_validation_status'],
                    'received_at' => $context['received_at'],
                    'processed_at' => $context['processed_at'],
                    'processing_status' => $context['processing_status'],
                    'error_details' => $context['error_details'],
                    'failed_payment_case_id' => $context['failed_payment_case_id'],
                    'metadata' => $context['metadata'],
                ]);

                $this->recordAuditLog($event, 'webhook_received', $context, [
                    'event_id' => $eventId,
                    'payload_hash' => $payloadHash,
                ]);

                return $event;
            });
        } catch (\Throwable $e) {
            Log::warning('Failed payment webhook capture failed', [
                'provider' => $context['provider'] ?? null,
                'event_id' => $context['event_id'] ?? null,
                'event_type' => $context['event_type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function sanitizePayload(mixed $payload): mixed
    {
        if (is_string($payload)) {
            return [
                'unparsed_payload_hash' => hash('sha256', $payload),
            ];
        }

        if (is_array($payload)) {
            $result = [];

            foreach ($payload as $key => $value) {
                $result[$key] = $this->sanitizeByKey((string) $key, $value);
            }

            return $this->sortRecursive($result);
        }

        if (is_object($payload)) {
            return $this->sanitizePayload((array) $payload);
        }

        return $payload;
    }

    protected function normalizeContext(array $context): array
    {
        $payload = $context['payload'] ?? [];

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded)
                ? $decoded
                : ['unparsed_payload_hash' => hash('sha256', $payload)];
        } elseif (is_object($payload)) {
            $payload = (array) $payload;
        }

        $provider = strtolower(trim((string) Arr::get($context, 'provider', 'authorize_net')));
        $eventId = trim((string) Arr::get($context, 'event_id', ''));
        $eventType = trim((string) Arr::get($context, 'event_type', ''));
        $signatureStatus = strtolower(trim((string) Arr::get($context, 'signature_validation_status', 'valid')));
        $processingStatus = strtolower(trim((string) Arr::get($context, 'processing_status', 'received')));

        return [
            'provider' => $provider !== '' ? $provider : 'authorize_net',
            'event_id' => $eventId !== '' ? $eventId : null,
            'event_type' => $eventType !== '' ? $eventType : null,
            'payload' => $payload,
            'signature_validation_status' => $signatureStatus !== '' ? $signatureStatus : 'valid',
            'received_at' => $this->datetimeOrNow(Arr::get($context, 'received_at')),
            'processed_at' => $this->datetimeOrNull(Arr::get($context, 'processed_at')),
            'processing_status' => $processingStatus !== '' ? $processingStatus : 'received',
            'error_details' => Arr::get($context, 'error_details'),
            'failed_payment_case_id' => Arr::get($context, 'failed_payment_case_id'),
            'metadata' => (array) Arr::get($context, 'metadata', []),
        ];
    }

    protected function duplicateDetectionPayload(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $payload = $payload;
            unset($payload['id']);

            return $payload;
        }

        return $payload;
    }

    protected function findExistingEvent(string $provider, ?string $eventId, string $payloadHash): ?FailedPaymentWebhookEvent
    {
        $query = FailedPaymentWebhookEvent::query()->where('provider', $provider);

        if (! empty($eventId)) {
            $existing = (clone $query)->where('event_id', $eventId)->first();
            if ($existing) {
                return $existing;
            }
        }

        return (clone $query)->where('payload_hash', $payloadHash)->first();
    }

    protected function recordAuditLog(FailedPaymentWebhookEvent $event, string $action, array $context, array $metadata = []): void
    {
        FailedPaymentAuditLog::create([
            'failed_payment_case_id' => $event->failed_payment_case_id,
            'previous_status' => null,
            'new_status' => null,
            'action' => $action,
            'source' => 'webhook',
            'admin_id' => null,
            'ip_address' => Arr::get($context, 'ip_address'),
            'safe_request_metadata' => [
                'provider' => $context['provider'] ?? null,
                'event_id' => $context['event_id'] ?? null,
                'event_type' => $context['event_type'] ?? null,
                'signature_validation_status' => $context['signature_validation_status'] ?? null,
                'processing_status' => $context['processing_status'] ?? null,
            ],
            'notes' => $action === 'webhook_duplicate'
                ? 'Duplicate webhook ignored by sanitized module-owned flow.'
                : 'Webhook captured in sanitized module-owned flow.',
            'metadata' => array_filter($metadata, fn ($value) => ! is_null($value) && $value !== ''),
        ]);
    }

    protected function sanitizeByKey(string $key, mixed $value): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeByKey((string) $childKey, $childValue);
            }

            return $this->sortRecursive($sanitized);
        }

        if (is_object($value)) {
            return $this->sanitizeByKey($key, (array) $value);
        }

        if (is_string($value) && $this->looksLikeCardNumber($value)) {
            return $this->maskCardNumber($value);
        }

        return $value;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $needle = strtolower($key);

        foreach ([
            'card_number',
            'cardnumber',
            'creditcard',
            'cvv',
            'cvc',
            'cvv2',
            'cvc2',
            'cvn',
            'cardcode',
            'securitycode',
            'pan',
            'password',
            'secret',
            'signature',
            'signature_key',
            'transaction_key',
            'api_login_id',
            'token',
            'access_token',
            'refresh_token',
            'merchantauthentication',
            'merchant_authentication',
            'account_number',
            'accountnumber',
            'routing_number',
            'routingnumber',
            'bank_account',
            'full_card_number',
        ] as $sensitive) {
            if (str_contains($needle, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeCardNumber(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 12 && strlen($digits) <= 19;
    }

    protected function maskCardNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $tail = substr($digits, -4);

        return $tail !== '' ? str_repeat('*', max(strlen($digits) - 4, 0)).$tail : '[redacted]';
    }

    protected function sortRecursive(array $value): array
    {
        $assoc = [];
        $indexed = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = $this->sortRecursive($item);
            }

            if (is_int($key)) {
                $indexed[$key] = $item;
            } else {
                $assoc[$key] = $item;
            }
        }

        ksort($assoc);

        return array_merge($assoc, $indexed);
    }

    protected function payloadHash(mixed $payload): string
    {
        $canonical = $this->canonicalize($payload);
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json === false ? '' : $json);
    }

    protected function canonicalize(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $result = [];

            foreach ($payload as $key => $value) {
                $result[$key] = $this->canonicalize($value);
            }

            if ($this->isAssoc($result)) {
                ksort($result);
            }

            return $result;
        }

        if (is_object($payload)) {
            return $this->canonicalize((array) $payload);
        }

        return $payload;
    }

    protected function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function datetimeOrNow(mixed $value): Carbon
    {
        $datetime = $this->datetimeOrNull($value);

        return $datetime ?? now();
    }

    protected function datetimeOrNull(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
