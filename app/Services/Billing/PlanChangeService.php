<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\PlanChangeLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Plan changes for an existing policyholder. Ported from the legacy
 * App\Services\PlanChangeService — same two jobs:
 *
 *  - preview(): read-only. Auto-suggests a credit/split (amount already paid
 *    → remaining → evenly split, remainder folded into today's charge) so the
 *    Edit Plan "Billing Actions" panel has sensible pre-filled numbers. Never
 *    a mandate — staff freely overwrite anything before applying.
 *
 *  - applyManual(): executes exactly the actions staff checked (charge today /
 *    update existing subscription / cancel / create new) with exactly the
 *    amounts/counts/dates they typed, always also saving the client's local
 *    plan fields, and writing one plan_change_logs row.
 *
 * The only decoupling from the legacy version: the *new plan price* is taken
 * as an input (NewPlanInput::effectivePrice()) rather than recomputed here
 * from the pricing engine — the proration math and gateway execution are
 * ported verbatim.
 */
class PlanChangeService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuthorizeAccountSelector $accounts,
    ) {}

    /* ================================================================
     | preview()
     * ================================================================ */

    public function preview(Client $client, NewPlanInput $new): array
    {
        $ctx = $this->buildSuggestionContext($client, $new);

        return [
            'status' => 200,
            'transition' => $ctx['transition'],
            'old_payment_structure' => (int) $client->payment_structure,
            'old_installment' => $client->installment !== null ? (int) $client->installment : null,
            'new_payment_structure' => $new->paymentStructure(),
            'new_installment_type' => $new->installmentOn ? $new->installmentType : 0,
            'new_total_price' => number_format($ctx['new_total_price'], 2),
            'amount_already_paid' => number_format($ctx['amount_already_paid'], 2),
            'suggested_remaining' => $ctx['remaining'] !== null ? number_format($ctx['remaining'], 2) : null,
            'suggested_overpaid_amount' => number_format($ctx['overpaid_amount'], 2),
            'suggested_charge_today' => number_format($ctx['immediate_charge'], 2),
            'suggested_new_subscription_amount' => $ctx['arb_amount'] !== null ? number_format($ctx['arb_amount'], 2) : null,
            'suggested_new_subscription_occurrences' => $ctx['arb_occurrences'],
            'suggested_new_subscription_start_date' => $ctx['arb_start_date']?->toDateString(),
            'suggested_surplus' => $ctx['surplus'] !== null ? number_format($ctx['surplus'], 2) : null,
            'suggested_skip_months' => $ctx['skip_months'],
            'existing_subscription_id' => $ctx['old_subscription_id'],
            'has_existing_subscription' => (bool) $ctx['old_subscription_id'],
            'message' => 'Successfully calculated plan change.',
        ];
    }

    /** @return array<string,mixed> */
    private function buildSuggestionContext(Client $client, NewPlanInput $new): array
    {
        $newPlanPriceOff = $new->effectivePrice();

        $oldStructure = (int) $client->payment_structure;
        $oldTermMonths = $this->oldTermMonths($client);
        $fromMonthly = $oldStructure === 1;

        $newInstallmentType = $new->installmentOn ? $new->installmentType : 0;

        if ($new->paymentStructure() === 1) {
            $transition = 'to_monthly';
        } elseif ($newInstallmentType >= 2) {
            $transition = 'to_installment';
        } else {
            $transition = 'to_full_pay';
        }

        $amountAlreadyPaid = $this->amountAlreadyPaid($client);
        if ($fromMonthly && $transition !== 'to_monthly') {
            $amountAlreadyPaid = 0.0;
        }

        $remaining = null;
        $overpaidAmount = 0.0;
        $immediateCharge = 0.0;
        $arbAmount = null;
        $arbOccurrences = null;
        $arbStartDate = null;
        $surplus = null;
        $skipMonths = null;

        if ($transition === 'to_monthly') {
            $newMonthlyPrice = $newPlanPriceOff;
            $skipMonths = 0;

            if ($oldStructure === 2) {
                $oldPlanTotal = $this->oldPlanTotalFromCache($client);
                $monthlyEquivalent = $oldTermMonths > 0 ? $oldPlanTotal / $oldTermMonths : $oldPlanTotal;
                $elapsedMonths = max(1, (int) floor($this->planStartedAt($client)->diffInDays(now()) / 30));
                $valueDelivered = $elapsedMonths * $monthlyEquivalent;
                $surplus = round($amountAlreadyPaid - $valueDelivered, 2);

                if ($surplus > 0 && $newMonthlyPrice > 0) {
                    $skipMonths = (int) floor($surplus / $newMonthlyPrice);
                    $leftover = round($surplus - ($skipMonths * $newMonthlyPrice), 2);
                    if ($leftover > 1.00) {
                        $overpaidAmount = $leftover;
                    }
                }
            } else {
                $surplus = 0.0;
            }

            $arbAmount = $newMonthlyPrice;
            $arbOccurrences = 999;
            $arbStartDate = Carbon::now()->addMonthsNoOverflow($skipMonths + 1);
        } else {
            $remaining = round($newPlanPriceOff - $amountAlreadyPaid, 2);

            if ($remaining <= 0) {
                $overpaidAmount = round(-$remaining, 2);
            } else {
                $n = max(1, $newInstallmentType >= 2 ? $newInstallmentType : 1);

                if ($n <= 1) {
                    $immediateCharge = $remaining;
                } else {
                    $immediateCharge = round($remaining / $n, 2);
                    $arbAmount = max(0, round(($remaining - $immediateCharge) / $n, 2));
                    $arbOccurrences = $n;
                    $arbStartDate = Carbon::now()->addMonthNoOverflow();
                }
            }
        }

        return [
            'transition' => $transition,
            'new_total_price' => $newPlanPriceOff,
            'amount_already_paid' => $amountAlreadyPaid,
            'remaining' => $remaining,
            'overpaid_amount' => $overpaidAmount,
            'immediate_charge' => $immediateCharge,
            'arb_amount' => $arbAmount,
            'arb_occurrences' => $arbOccurrences,
            'arb_start_date' => $arbStartDate,
            'surplus' => $surplus,
            'skip_months' => $skipMonths,
            'old_subscription_id' => $this->oldSubscriptionId($client),
        ];
    }

    /* ================================================================
     | applyManual()
     * ================================================================ */

    /**
     * @return array{ok:bool, log_id:int, cancel_result:?string, charge_today_amount:float, new_subscription_id:?string}
     */
    public function applyManual(Client $client, NewPlanInput $new, BillingActions $actions, ?int $staffUserId): array
    {
        $oldSubscriptionId = null;
        $cancelAccount = null;

        if ($actions->cancelSubscription || $actions->updateExistingSubscription || $actions->createNewSubscription) {
            [$oldSubscriptionId, $cancelAccount] = $this->resolveOldSubscription($client);
        }

        if (($actions->cancelSubscription || $actions->updateExistingSubscription) && ! $oldSubscriptionId) {
            throw new \RuntimeException('No existing Authorize.Net subscription was found for this client to update or cancel.');
        }

        if ($actions->createNewSubscription && ! $actions->cancelSubscription && $oldSubscriptionId) {
            throw new \RuntimeException("This client already has an active subscription (#{$oldSubscriptionId}). Check Cancel above too when replacing it, or use Update instead — otherwise they'd end up with two subscriptions billing at once.");
        }

        $chargingProfile = ($actions->chargeToday || $actions->createNewSubscription)
            ? $this->resolveChargingProfile($client, $actions)
            : ['card_profile' => null, 'account' => null, 'customer_profile_id' => null, 'payment_profile_id' => null];

        $oldTermMonths = $this->oldTermMonths($client);

        $log = PlanChangeLog::create([
            'client_id' => $client->id,
            'changed_by_user_id' => $staffUserId,
            'old_package_id' => $client->package,
            'old_payment_structure' => (int) $client->payment_structure,
            'old_installment' => $client->installment !== null ? (int) $client->installment : null,
            'old_term_months' => $oldTermMonths,
            'new_package_id' => $new->packageId,
            'new_payment_structure' => $new->paymentStructure(),
            'new_installment_type' => $new->installmentOn ? $new->installmentType : 0,
            'new_term_months' => $new->termMonths(),
            'amount_already_paid' => $this->amountAlreadyPaid($client),
            'new_total_price' => $new->effectivePrice(),
            'immediate_charge_amount' => $actions->chargeTodayAmount,
            'new_installment_amount' => $actions->createNewSubscription ? $actions->newSubscriptionAmount : $actions->updateExistingAmount,
            'new_installment_occurrences' => $actions->newSubscriptionOccurrences,
            'new_arb_start_date' => $actions->newSubscriptionStartDate?->toDateString(),
            'old_subscription_id' => $oldSubscriptionId,
            'status' => 'pending',
        ]);

        // Step A — cancel the existing subscription.
        $cancelResult = null;
        if ($actions->cancelSubscription) {
            $cancelResult = $this->gateway->cancelSubscription($cancelAccount, $oldSubscriptionId);

            if (! in_array($cancelResult, ['ok', 'already_canceled'], true)) {
                $log->update(['status' => 'failed', 'failure_stage' => 'cancel_old', 'old_cancel_result' => $cancelResult, 'error_message' => 'Failed to cancel the existing Authorize.Net subscription.']);

                throw new \RuntimeException("Failed to cancel the existing subscription at Authorize.Net. Nothing else was changed — please retry. (plan_change_logs #{$log->id})");
            }

            $log->update(['old_cancel_result' => $cancelResult]);

            PaymentHistory::where('client_id', $client->id)
                ->where('subscription_id', $oldSubscriptionId)
                ->where('payment_status', 0)
                ->delete();
        }

        // Step B — update the existing subscription amount in place.
        if ($actions->updateExistingSubscription) {
            try {
                $this->gateway->updateSubscriptionAmount($cancelAccount, $oldSubscriptionId, $actions->updateExistingAmount);
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed', 'failure_stage' => 'update_existing', 'error_message' => $e->getMessage()]);

                throw new \RuntimeException("Failed to update the existing subscription's amount: {$e->getMessage()} (plan_change_logs #{$log->id})");
            }

            PaymentHistory::where('client_id', $client->id)
                ->where('subscription_id', $oldSubscriptionId)
                ->where('payment_status', 0)
                ->update(['amount' => $actions->updateExistingAmount]);
        }

        // Step C — charge today.
        $transactionId = null;
        if ($actions->chargeToday) {
            try {
                $transactionId = $this->gateway->chargeProfile(
                    $chargingProfile['account'],
                    (string) $chargingProfile['customer_profile_id'],
                    (string) $chargingProfile['payment_profile_id'],
                    $actions->chargeTodayAmount,
                    'Plan change - manual charge',
                )['transaction_id'];
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed_partial', 'failure_stage' => 'charge_today', 'error_message' => $e->getMessage()]);

                throw new \RuntimeException("Charge failed: {$e->getMessage()}. (plan_change_logs #{$log->id})");
            }
        }

        // Always — save local plan fields + anchor row.
        $anchorRow = DB::transaction(function () use ($client, $new, $actions, $transactionId, $chargingProfile) {
            $this->applyClientFields($client, $new, $actions);

            if ($actions->chargeToday) {
                return PaymentHistory::create([
                    'client_id' => $client->id,
                    'plan_id' => (string) time(),
                    'purpose' => 'Plan change - manual charge',
                    'instalment' => ($new->installmentOn && $new->installmentType >= 2) ? 1 : 0,
                    'amount' => $actions->chargeTodayAmount,
                    'currency' => '$',
                    'payment_date' => now()->toDateString(),
                    'status' => 1,
                    'payment_status' => 1,
                    'transaction_id' => $transactionId,
                    'authorize_account_id' => $chargingProfile['account']?->id,
                    'card_number' => $chargingProfile['card_profile']?->last4,
                    'expMonth' => $chargingProfile['card_profile']?->expMonth,
                    'expYear' => $chargingProfile['card_profile']?->expYear,
                    'is_custom' => 0,
                    'claim_payment' => 0,
                ]);
            }

            if ($actions->createNewSubscription) {
                return PaymentHistory::where('client_id', $client->id)->latest('id')->first();
            }

            return null;
        });

        $log->update(['status' => 'completed']);

        // Step D — create a new subscription.
        $newSubscriptionId = null;
        if ($actions->createNewSubscription) {
            try {
                $newSubscriptionId = $this->gateway->createSubscriptionFromProfile(
                    $chargingProfile['account'],
                    (string) $chargingProfile['customer_profile_id'],
                    (string) $chargingProfile['payment_profile_id'],
                    $actions->newSubscriptionStartDate,
                    $actions->newSubscriptionAmount,
                    ((string) $client->installment) === '2' ? 12 : 1,
                    'months',
                    $actions->newSubscriptionOccurrences,
                    trim(($client->first_name ?? '').' Subscription'),
                );
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed_partial', 'failure_stage' => 'create_new', 'error_message' => $e->getMessage()]);

                throw new \RuntimeException("Local changes and any charge were saved, but the new subscription could not be created: {$e->getMessage()} (plan_change_logs #{$log->id})");
            }

            if ($anchorRow) {
                $anchorRow->update(['subscription_id' => $newSubscriptionId]);
            }

            $log->update(['new_subscription_id' => $newSubscriptionId]);
        }

        if ($actions->anyBillingAction()) {
            $client->forceFill(['plan_changed_at' => now()])->save();
        }

        $client->refresh();

        return [
            'ok' => true,
            'log_id' => $log->id,
            'cancel_result' => $cancelResult,
            'charge_today_amount' => $actions->chargeToday ? (float) $actions->chargeTodayAmount : 0.0,
            'new_subscription_id' => $newSubscriptionId,
        ];
    }

    /* ================================================================
     | helpers (ported verbatim)
     * ================================================================ */

    public function amountAlreadyPaid(Client $client): float
    {
        $query = PaymentHistory::where('client_id', $client->id)
            ->where('payment_status', 1)
            ->where('claim_payment', 0)
            ->where('is_custom', 0)
            ->where('amount', '!=', 0);

        if ($client->plan_changed_at) {
            $cutoff = Carbon::parse($client->plan_changed_at);
            $query->where(function ($q) use ($cutoff) {
                $q->where('payment_date', '>=', $cutoff->toDateString())
                    ->orWhere(fn ($q2) => $q2->whereNull('payment_date')->where('created_at', '>=', $cutoff));
            });
        }

        return (float) $query->sum('amount');
    }

    private function oldTermMonths(Client $client): int
    {
        if ((int) $client->payment_structure !== 2) {
            return 0;
        }

        $start = $client->custom_s_date ?: $client->start_date;
        $end = $client->custom_e_date ?: $client->end_date;

        if (! $start || ! $end) {
            return 12;
        }

        return max(1, (int) Carbon::parse($start)->diffInMonths(Carbon::parse($end)));
    }

    private function planStartedAt(Client $client): Carbon
    {
        if ($client->plan_changed_at) {
            return Carbon::parse($client->plan_changed_at);
        }

        return Carbon::parse($client->custom_s_date ?: ($client->start_date ?: ($client->created_at ?: now())));
    }

    private function oldPlanTotalFromCache(Client $client): float
    {
        $cache = json_decode($client->price_cal_response ?? '{}', true) ?: [];

        $perUnit = (float) str_replace(',', '', (string) ($cache['discount_price'] ?? 0));
        if ($perUnit === 0.0) {
            $perUnit = (float) str_replace(',', '', (string) ($cache['price'] ?? 0));
        }

        $oldInstallmentCount = (int) ($client->installment ?? 0);

        return round($oldInstallmentCount >= 2 ? $perUnit * $oldInstallmentCount : $perUnit, 2);
    }

    private function oldSubscriptionId(Client $client): ?string
    {
        return PaymentHistory::where('client_id', $client->id)
            ->whereNotNull('subscription_id')
            ->latest('id')
            ->value('subscription_id');
    }

    /** @return array{0:?string, 1:?AuthorizeAccount} */
    private function resolveOldSubscription(Client $client): array
    {
        $seed = PaymentHistory::where('client_id', $client->id)
            ->whereNotNull('subscription_id')
            ->latest('id')
            ->first();

        $subscriptionId = $seed?->subscription_id ? (string) $seed->subscription_id : null;

        if (! $subscriptionId) {
            return [null, null];
        }

        $account = AuthorizeAccount::find($seed->authorize_account_id)
            ?? $this->accounts->bySubscriptionId($subscriptionId)
            ?? $this->accounts->pickForCharge();

        if (! $account) {
            throw new \RuntimeException("Could not find the Authorize.Net account for this client's existing subscription (#{$subscriptionId}).");
        }

        return [$subscriptionId, $account];
    }

    /**
     * @return array{card_profile:?CardProfile, account:?AuthorizeAccount, customer_profile_id:?string, payment_profile_id:?string}
     */
    private function resolveChargingProfile(Client $client, BillingActions $actions): array
    {
        if ($actions->useNewCard) {
            $account = $this->accounts->pickForCharge();
            if (! $account) {
                throw new \RuntimeException('No active Authorize.Net account is configured.');
            }

            $ids = $this->gateway->createOpaqueProfile($account, [
                'first_name' => (string) $client->first_name,
                'last_name' => (string) $client->last_name,
                'email' => (string) $client->email,
                'address' => (string) $client->address,
                'city' => (string) $client->city,
                'zip' => (string) $client->zip_code,
            ], $actions->newCardDataDescriptor, $actions->newCardDataValue);

            $profile = CardProfile::create([
                'client_id' => $client->id,
                'authorize_account_id' => $account->id,
                'customer_profile_id' => $ids['customer_profile_id'],
                'payment_profile_id' => $ids['payment_profile_id'],
                'last4' => $actions->newCardLast4,
                'expMonth' => $actions->newCardExpMonth,
                'expYear' => $actions->newCardExpYear,
                'is_default' => 0,
            ]);

            return ['card_profile' => $profile, 'account' => $account, 'customer_profile_id' => $ids['customer_profile_id'], 'payment_profile_id' => $ids['payment_profile_id']];
        }

        $profile = CardProfile::where('client_id', $client->id)
            ->whereNotNull('customer_profile_id')
            ->whereNotNull('payment_profile_id')
            ->orderByDesc('id')
            ->first();

        if (! $profile) {
            throw new \RuntimeException('No usable saved card on file for this client.');
        }

        $account = AuthorizeAccount::find($profile->authorize_account_id);
        if (! $account) {
            throw new \RuntimeException("Card profile references a missing Authorize.Net account (id={$profile->authorize_account_id}).");
        }

        return ['card_profile' => $profile, 'account' => $account, 'customer_profile_id' => $profile->customer_profile_id, 'payment_profile_id' => $profile->payment_profile_id];
    }

    private function applyClientFields(Client $client, NewPlanInput $new, BillingActions $actions): void
    {
        $trueInstallmentCount = $actions->createNewSubscription ? (int) $actions->newSubscriptionOccurrences : null;

        if ($new->paymentStructure() === 1) {
            $client->payment_structure = 1;
            $client->end_date = null;
            $client->installment = null;
        } else {
            $client->payment_structure = 2;
            $client->end_date = Carbon::now()->addMonths($new->termMonths())->toDateString();
            $client->installment = $trueInstallmentCount !== null
                ? ($trueInstallmentCount >= 2 ? (string) $trueInstallmentCount : null)
                : ($new->installmentType >= 2 ? (string) $new->installmentType : null);
        }

        $client->property_size = $new->propertySize ?? $client->property_size;
        $client->property_type = $new->propertyType ?? $client->property_type;
        $client->package = $new->packageId ?? $client->package;
        $client->services = $new->addonIds !== [] ? json_encode($new->addonIds) : $client->services;
        $client->discount = $new->discountValue ?: $client->discount;
        $client->call_fee = $new->serviceCallFee ?? $client->call_fee;
        $client->salesman_name = $new->salesmanName ?? $client->salesman_name;
        $client->description = $new->clientNotes ?? $client->description;
        $client->custom_price = filled($new->customPrice)
            ? number_format((float) preg_replace('/[^0-9.]/', '', (string) $new->customPrice), 2)
            : null;

        if (filled($new->customPurchaseDate)) {
            $client->custom_purchaes_date = $new->customPurchaseDate;
        }
        if (filled($new->coverageStartDate)) {
            if ($client->custom_s_date || $client->custom_e_date) {
                $client->custom_s_date = $new->coverageStartDate;
            } else {
                $client->start_date = $new->coverageStartDate;
            }
        }

        $client->save();
    }
}
