<?php

namespace App\Services\Reporting;

use App\Models\AnetWebhookEvent;
use App\Models\AuthorizeAccount;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\Anet\ArbGateway;
use Illuminate\Support\Collection;

/**
 * ANet Recurring Health. Ported from the legacy AuthorizeNetReportService.
 * `accountSummaries` and `buildMismatchStatus` are verbatim; `checkSubscription`
 * is rewired from the dead SDK onto the Phase 6c ArbGateway and is gated by
 * config('reporting.anet_live_checks') so the report never calls out on load.
 */
class AnetReportService
{
    public function __construct(private readonly ArbGateway $arb) {}

    public function accountSummaries(): Collection
    {
        return AuthorizeAccount::query()
            ->orderByDesc('is_active')->orderBy('id')->get()
            ->map(fn (AuthorizeAccount $account) => [
                'account' => $account,
                'local_subscription_count' => PaymentHistory::query()
                    ->where('authorize_account_id', $account->id)
                    ->whereNotNull('subscription_id')->where('subscription_id', '!=', '')
                    ->where('claim_payment', 0)->where('is_custom', 0)
                    ->distinct('subscription_id')->count('subscription_id'),
                'local_active_clients' => Client::query()->where('authorize_account_id', $account->id)->where('status', 1)->count(),
                'local_failed_payments' => PaymentHistory::query()->where('authorize_account_id', $account->id)->where('payment_status', 0)->count(),
                'webhook_event_count' => AnetWebhookEvent::query()->where('authorize_account_id', $account->id)->count(),
                'last_webhook_at' => AnetWebhookEvent::query()->where('authorize_account_id', $account->id)->latest('created_at')->value('created_at'),
            ]);
    }

    public function totals(Collection $summaries): array
    {
        return [
            'accounts' => $summaries->count(),
            'active_accounts' => $summaries->filter(fn ($r) => $r['account']->is_active)->count(),
            'local_subscriptions' => $summaries->sum('local_subscription_count'),
            'failed_payments' => $summaries->sum('local_failed_payments'),
            'webhook_events' => $summaries->sum('webhook_event_count'),
        ];
    }

    /** @return array{status:string, gateway_status:?string, matched_account_id:?int, matched_account_name:?string, message:?string} */
    public function checkSubscription(PaymentHistory $payment): array
    {
        $subscriptionId = trim((string) $payment->subscription_id);

        if ($subscriptionId === '') {
            return $this->result('missing', null, 'No subscription id found on this payment row.');
        }

        if (! config('reporting.anet_live_checks')) {
            return $this->result('skipped', null, 'Live gateway checks are disabled.');
        }

        foreach ($this->candidateAccounts($payment) as $account) {
            try {
                $status = $this->arb->getSubscriptionStatus($account, $subscriptionId);

                if ($status !== null) {
                    return $this->result('ok', $account, null, $status);
                }
            } catch (\Throwable) {
                // try the next candidate account
            }
        }

        return $this->result('error', null, 'Subscription could not be verified against the configured Authorize.Net accounts.');
    }

    public function buildMismatchStatus(PaymentHistory $payment, array $liveCheck): array
    {
        $gatewayStatus = strtolower((string) ($liveCheck['gateway_status'] ?? ''));
        $client = $payment->userInfo;

        $localClientActive = (int) ($client?->status ?? 0) === 1;
        $localPaymentFailed = (int) ($payment->payment_status ?? 0) === 0;
        $localAuthorizeStatus = strtolower((string) ($client?->authorize_status ?? ''));

        if (($liveCheck['status'] ?? '') !== 'ok') {
            return ['level' => 'warning', 'label' => 'Needs review', 'message' => $liveCheck['message'] ?? 'Live status unavailable.'];
        }

        $gatewayActive = $gatewayStatus === 'active';
        $gatewayClosed = in_array($gatewayStatus, ['canceled', 'cancelled', 'expired', 'terminated', 'suspended'], true);

        if ($gatewayActive && (! $localClientActive || $localPaymentFailed)) {
            return ['level' => 'danger', 'label' => 'Mismatch', 'message' => 'Gateway is active but local client/payment is not fully active.'];
        }

        if ($gatewayClosed && $localClientActive) {
            return ['level' => 'danger', 'label' => 'Mismatch', 'message' => 'Local client is active but gateway subscription is not active.'];
        }

        if ($localAuthorizeStatus !== '' && $gatewayStatus !== '' && $localAuthorizeStatus !== $gatewayStatus) {
            return ['level' => 'warning', 'label' => 'Status differs', 'message' => 'Client authorize_status does not match the gateway status.'];
        }

        return ['level' => 'success', 'label' => 'Matched', 'message' => 'Local status and gateway status look aligned.'];
    }

    protected function candidateAccounts(PaymentHistory $payment): Collection
    {
        $accountIds = collect([$payment->authorize_account_id, $payment->userInfo?->authorize_account_id])
            ->filter()->unique()->values();

        $preferred = AuthorizeAccount::query()->whereIn('id', $accountIds)->orderByDesc('is_active')->get();
        $fallback = AuthorizeAccount::query()
            ->when($accountIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $accountIds))
            ->orderByDesc('is_active')->orderBy('id')->get();

        return $preferred->concat($fallback);
    }

    protected function result(string $status, ?AuthorizeAccount $account = null, ?string $message = null, ?string $gatewayStatus = null): array
    {
        return [
            'status' => $status,
            'gateway_status' => $gatewayStatus,
            'matched_account_id' => $account?->id,
            'matched_account_name' => $account?->name,
            'message' => $message,
        ];
    }
}
