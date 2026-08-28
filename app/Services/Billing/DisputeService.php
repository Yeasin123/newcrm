<?php

namespace App\Services\Billing;

use App\Models\PaymentHealthEvent;
use App\Models\PaymentHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chargeback / dispute tracking. Ports the relevant slice of the legacy
 * PaymentRefundController::paymentDispute* methods:
 *
 *   - markDisputed(): flag the payment, open a payment_health_events row, and
 *     (when outbound webhooks are enabled) POST to POLICY_DISPUTE_WEBHOOK_URL.
 *   - resolveDispute(): record win / loss and resolve the event.
 *   - resetDispute(): clear the flags and reopen.
 */
class DisputeService
{
    public function markDisputed(PaymentHistory $payment, ?string $note = null): PaymentHealthEvent
    {
        return DB::transaction(function () use ($payment, $note) {
            $payment->forceFill(['disputed' => 1, 'win' => 0, 'loss' => 0])->save();

            $event = PaymentHealthEvent::create([
                'client_id' => $payment->client_id,
                'payment_history_id' => $payment->id,
                'authorize_account_id' => $payment->authorize_account_id,
                'event_type' => 'dispute_opened',
                'status' => 'open',
                'title' => 'Dispute opened on payment #'.$payment->id,
                'description' => $note,
                'payload' => ['amount' => $payment->amount, 'transaction_id' => $payment->transaction_id],
            ]);

            $this->fireWebhook($payment, 'opened', $note);

            return $event;
        });
    }

    /** @param 'win'|'loss' $outcome */
    public function resolveDispute(PaymentHistory $payment, string $outcome, ?string $note = null): void
    {
        DB::transaction(function () use ($payment, $outcome, $note) {
            $payment->forceFill([
                'win' => $outcome === 'win' ? 1 : 0,
                'loss' => $outcome === 'loss' ? 1 : 0,
            ])->save();

            PaymentHealthEvent::where('payment_history_id', $payment->id)
                ->where('event_type', 'dispute_opened')
                ->where('status', 'open')
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => Carbon::now(),
                    'title' => 'Dispute '.$outcome.' on payment #'.$payment->id,
                ]);

            $this->fireWebhook($payment, $outcome, $note);
        });
    }

    public function resetDispute(PaymentHistory $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->forceFill(['disputed' => 0, 'win' => 0, 'loss' => 0])->save();

            PaymentHealthEvent::where('payment_history_id', $payment->id)
                ->where('event_type', 'dispute_opened')
                ->delete();
        });
    }

    private function fireWebhook(PaymentHistory $payment, string $stage, ?string $note): void
    {
        $url = env('POLICY_DISPUTE_WEBHOOK_URL');

        if (! config('billing.outbound_webhooks_enabled') || ! $url) {
            return;
        }

        try {
            Http::timeout(8)->asJson()->post($url, [
                'client_id' => $payment->client_id,
                'payment_history_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount,
                'stage' => $stage,
                'note' => $note,
                'at' => Carbon::now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Dispute webhook failed', ['payment_history_id' => $payment->id, 'stage' => $stage, 'error' => $e->getMessage()]);
        }
    }
}
