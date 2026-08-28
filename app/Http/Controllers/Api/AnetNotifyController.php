<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\AnetPaymentHistoryResolver;
use App\Services\Billing\AnetSignatureVerifier;
use App\Services\Billing\AnetTransactionExpander;
use App\Services\Billing\AnetWebhookRecorder;
use App\Services\Billing\PaymentOutcomeNotifier;
use App\Services\FailedPayment\FailedPaymentCaptureService;
use App\Services\FailedPayment\FailedPaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Inbound Authorize.Net notify webhooks. Ported from the legacy
 * `Admin\TransactionCheckController` — behaviour and response are kept
 * byte-identical:
 *
 *   - x-anet-signature (sha512) is verified against every merchant account;
 *   - a missing/invalid signature, bad JSON, missing ids, or ANY thrown error
 *     still returns HTTP 200 {status:"success"} (never let Authorize.Net retry);
 *   - duplicates (by anet_webhook_events.event_id) return {status:"duplicate_ignored"};
 *   - suspend / terminated flip clients.status = 0, set payment_faild_date,
 *     backfill skipped monthly payment_histories rows, and fire the outbound
 *     payment-outcome notifier.
 *
 * Deferred to Phase 6c (gateway SDK): the create-notify handler's
 * `getSubscriptionInfo` transaction fetch, and the terminated-handler's
 * cross-account subscription probe. Until then the event is still recorded.
 */
class AnetNotifyController
{
    public function __construct(
        private readonly AnetSignatureVerifier $verifier,
        private readonly AnetWebhookRecorder $recorder,
        private readonly AnetPaymentHistoryResolver $resolver,
        private readonly PaymentOutcomeNotifier $notifier,
        private readonly AnetTransactionExpander $expander,
        private readonly FailedPaymentWebhookService $fpWebhook,
        private readonly FailedPaymentCaptureService $fpCapture,
    ) {}

    private function ok(string $status = 'success'): JsonResponse
    {
        return response()->json(['status' => $status]);
    }

    /**
     * Record the sanitized webhook into the Failed Payment module's own
     * idempotency store (mirrors the legacy captureFailedPaymentWebhookEvent).
     * The module's flags gate whether anything is actually persisted; failures
     * are swallowed so the notify response is never affected.
     */
    private function fpRecordWebhook(array $json, string $eventId, string $eventType, ?string $payloadId, string $sourceMethod): void
    {
        try {
            $this->fpWebhook->recordReceived([
                'provider' => 'authorize_net',
                'event_id' => $eventId !== '' ? $eventId : null,
                'event_type' => $eventType !== '' ? $eventType : null,
                'payload' => $json,
                'signature_validation_status' => 'valid',
                'processing_status' => 'received',
                'received_at' => now(),
                'metadata' => ['source_method' => $sourceMethod, 'payload_id' => $payloadId],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FailedPayment webhook capture failed', ['error' => $e->getMessage(), 'event_id' => $eventId]);
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $raw = (string) $request->getContent();
            $sig = trim((string) $request->header('x-anet-signature'));

            if ($sig === '' || stripos($sig, 'sha512=') !== 0) {
                Log::warning('ANET create webhook missing signature');

                return $this->ok();
            }

            $acc = $this->verifier->detectAccount($raw, $sig);
            if (! $acc) {
                Log::warning('ANET create webhook signature mismatch', ['signature_prefix' => substr($sig, 0, 16)]);

                return $this->ok();
            }

            $json = json_decode($raw, true);
            if (! is_array($json)) {
                Log::warning('ANET create webhook invalid json', ['authorize_account_id' => $acc->id]);

                return $this->ok();
            }

            $eventId = (string) ($json['id'] ?? '');
            $eventType = (string) ($json['eventType'] ?? '');
            $payload = is_array($json['payload'] ?? null) ? $json['payload'] : [];
            $payloadId = (string) ($payload['id'] ?? '');

            $this->fpRecordWebhook($json, $eventId, $eventType, $payloadId ?: null, 'transactionCreateNotify');

            if ($this->recorder->eventExists($eventId)) {
                return $this->ok('duplicate_ignored');
            }

            $isTransactionEvent = in_array($eventType, [
                'net.authorize.payment.authcapture.created',
                'net.authorize.payment.authorization.created',
                'net.authorize.payment.capture.created',
            ], true);

            if ($isTransactionEvent && $payloadId !== '') {
                // Expand the transaction into a paid payment_histories row when
                // gateway reads are enabled (config-gated inside the expander).
                try {
                    $this->expander->expand($acc, $payloadId);
                } catch (\Throwable $e) {
                    Log::warning('ANET create webhook: transaction expand failed', [
                        'authorize_account_id' => $acc->id, 'transaction_id' => $payloadId, 'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->recorder->record($acc, $eventId, $eventType, $payloadId ?: null, $raw);

            return $this->ok();
        } catch (\Throwable $e) {
            Log::error('ANET create webhook unexpected error', ['error' => $e->getMessage(), 'line' => $e->getLine()]);

            return $this->ok();
        }
    }

    public function suspend(Request $request): JsonResponse
    {
        return $this->handleFailure($request, 'suspended', 'net.authorize.customer.subscription.suspended');
    }

    public function terminated(Request $request): JsonResponse
    {
        return $this->handleFailure($request, 'terminated', 'net.authorize.customer.subscription.terminated');
    }

    /**
     * Shared body for suspend + terminated — they differ only in the event
     * type string and cache key. Ported from the two legacy methods (which
     * were near-identical).
     */
    private function handleFailure(Request $request, string $cacheKey, string $expectedEventType): JsonResponse
    {
        try {
            $raw = (string) $request->getContent();
            $sig = (string) $request->header('x-anet-signature');

            $acc = $this->verifier->detectAccount($raw, $sig);
            if (! $acc) {
                Log::warning("ANET {$cacheKey} webhook invalid signature", ['signature_prefix' => substr(trim($sig), 0, 16)]);

                return $this->ok();
            }

            $json = json_decode($raw, true);
            if (! is_array($json)) {
                Log::warning("ANET {$cacheKey} webhook invalid json", ['authorize_account_id' => $acc->id]);

                return $this->ok();
            }

            $eventId = (string) ($json['id'] ?? '');
            $eventType = (string) ($json['eventType'] ?? '');
            $payload = is_array($json['payload'] ?? null) ? $json['payload'] : [];
            $payloadId = isset($payload['id']) ? (string) $payload['id'] : null;

            $this->fpRecordWebhook($json, $eventId, $eventType, $payloadId, $cacheKey === 'suspended' ? 'transactionSuspendNotify' : 'transactionTerminatedNotify');

            if ($this->recorder->eventExists($eventId)) {
                return $this->ok('duplicate_ignored');
            }

            $webhookAmount = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;

            if ($eventType === $expectedEventType) {
                $eventDateRaw = $json['eventDate'] ?? null;
                $subsId = $payload['id'] ?? null;

                if (! $eventDateRaw || ! $subsId) {
                    Log::warning("ANET {$cacheKey} webhook missing eventDate or subscription id", [
                        'authorize_account_id' => $acc->id, 'event_id' => $eventId,
                    ]);

                    return $this->ok();
                }

                $paymentDateTime = $this->resolver->normalizePaymentDateTime($eventDateRaw);
                $eventDate = Carbon::parse($eventDateRaw);

                $paymentH = $this->resolver->findSubscriptionPaymentHistory((string) $subsId, $acc);

                if (! $paymentH) {
                    Log::warning("ANET {$cacheKey} payment history seed not found", [
                        'authorize_account_id' => $acc->id, 'subscription_id' => (string) $subsId, 'event_id' => $eventId,
                    ]);
                    Cache::forever($cacheKey.'_missing_paymenthistory_'.$subsId.'_acc_'.$acc->id, $request->all());
                    $this->recorder->record($acc, $eventId, $eventType, $payloadId, $raw);
                    Cache::forever($cacheKey.'_'.$acc->id, $request->all());

                    return $this->ok();
                }

                $client = Client::find($paymentH->client_id);

                DB::transaction(function () use ($paymentH, $acc, $paymentDateTime, $client, $eventDate, $webhookAmount, $subsId, $cacheKey) {
                    PaymentHistory::whereKey($paymentH->id)->lockForUpdate()->first();

                    $expectedAmount = $this->resolver->expectedRecurringAmount($client, $paymentH);

                    if ($webhookAmount > 0 && abs($webhookAmount - $expectedAmount) > 0.01) {
                        Log::warning("ANET {$cacheKey} webhook amount mismatch; using expected recurring amount", [
                            'authorize_account_id' => $acc->id,
                            'subscription_id' => (string) $subsId,
                            'payment_history_id' => $paymentH->id,
                            'payload_amount' => $webhookAmount,
                            'expected_amount' => $expectedAmount,
                            'client_id' => $client?->id,
                        ]);
                    }

                    if ($client) {
                        $this->resolver->createMissingMonthlyPayments($client, $paymentH, $eventDate, $expectedAmount, $acc->id);
                    }

                    $this->resolver->createPaymentAttemptRowIfMissing(
                        $paymentH, $acc, $paymentDateTime, null, 0, $expectedAmount, $paymentH->payment_type,
                    );

                    if ($client) {
                        $client->status = 0;
                        $client->payment_faild_date = $eventDate;
                        $client->save();
                    }
                });

                $this->notifier->notify($client, false, $eventDate);

                // Passive capture into the Failed Payment module (no-op unless
                // its module_enabled + capture_enabled flags are on).
                try {
                    $this->fpCapture->capture([
                        'client_id' => $client?->id,
                        'payment_history_id' => $paymentH->id,
                        'authorize_account_id' => $acc->id,
                        'anet_webhook_event_id' => $eventId,
                        'arb_subscription_id' => (string) $subsId,
                        'anet_transaction_id' => null,
                        'failed_amount' => $webhookAmount ?: (float) $paymentH->amount,
                        'currency' => $paymentH->currency ?? null,
                        'failure_code' => null,
                        'failure_reason' => "Authorize.Net subscription {$cacheKey} webhook.",
                        'failure_category' => $cacheKey,
                        'failure_source' => 'authorize_net_webhook',
                        'payment_frequency' => (int) $paymentH->payment_type === 1 ? 'bank' : 'card',
                        'scheduled_payment_date' => $paymentDateTime,
                        'first_failed_at' => $eventDate,
                        'latest_failed_at' => $eventDate,
                        'metadata' => [
                            'event_id' => $eventId,
                            'event_type' => $eventType,
                            'payload_id' => $payloadId,
                            'source_method' => $cacheKey === 'suspended' ? 'transactionSuspendNotify' : 'transactionTerminatedNotify',
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('FailedPayment passive capture skipped', ['error' => $e->getMessage(), 'payment_history_id' => $paymentH->id]);
                }
            } else {
                Log::info("ANET {$cacheKey} webhook ignored event type", [
                    'authorize_account_id' => $acc->id, 'event_id' => $eventId, 'event_type' => $eventType,
                ]);
            }

            $this->recorder->record($acc, $eventId, $eventType, $payloadId, $raw);
            Cache::forever($cacheKey.'_'.$acc->id, $request->all());

            return $this->ok();
        } catch (\Throwable $e) {
            Log::error("ANET {$cacheKey} webhook unexpected error", ['error' => $e->getMessage(), 'line' => $e->getLine()]);

            return $this->ok();
        }
    }
}
