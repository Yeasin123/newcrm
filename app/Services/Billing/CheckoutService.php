<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Models\Bonus;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Models\PaymentLink;
use App\Models\SaleCommission;
use App\Models\User;
use App\Services\Commission\CommissionCalculator;
use App\Support\CardBrand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Subscription checkout. Faithful port of the legacy
 * SubscriptionController::{monthly,installment,yearly}SubcriptonTransaction
 * plus the Payment\StripePaymentController::stripeSubscriptionPayment
 * dispatcher, with the gateway calls routed through PaymentGateway (dry-run
 * by default). Business rules kept intact:
 *
 *   - charge first (authCapture), then create the ARB subscription;
 *   - ARB starts one month out; monthly/yearly = 999 occurrences,
 *     installment = remaining count only;
 *   - on success: write a paid PaymentHistory row, backfill subscription_id
 *     onto every history row for the client, set clients.status=1 +
 *     bypass_payment=0 + start_date, bump paid_installment for installments;
 *   - salesman commission = amount - 10%, doubled when bonuses.commission_double.
 */
class CheckoutService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuthorizeAccountSelector $accounts,
    ) {}

    /** Dispatch on the client's plan shape (legacy stripeSubscriptionPayment router). */
    public function run(Client $client, float $amount, PaymentInput $payment, array $options = []): PaymentHistory
    {
        $structure = (string) $client->payment_structure;

        if ($structure === '1') {
            return $this->monthly($client, $amount, $payment, $options);
        }

        if ($structure === '2' && filled($client->installment)) {
            $count = (int) ($options['installment_count'] ?? 0) ?: 12;

            return $this->installment($client, $amount, $count, $payment, $options);
        }

        if ($structure === '2') {
            return $this->yearly($client, $amount, $payment, $options);
        }

        throw new \InvalidArgumentException("Client {$client->id} has no billable payment_structure.");
    }

    public function monthly(Client $client, float $amount, PaymentInput $payment, array $options = []): PaymentHistory
    {
        return $this->subscribe($client, round($amount, 2), $payment, $options, 1, 999, '(monthly)');
    }

    public function yearly(Client $client, float $amount, PaymentInput $payment, array $options = []): PaymentHistory
    {
        return $this->subscribe($client, round($amount, 2), $payment, $options, 12, 999, '(yearly)');
    }

    public function installment(Client $client, float $amount, int $installmentCount, PaymentInput $payment, array $options = []): PaymentHistory
    {
        $installmentNo = (int) $client->paid_installment + 1;
        $remaining = max($installmentCount - $installmentNo, 0);

        $unitLen = ((string) $client->installment) === '2' ? 12 : 1;
        $typeLabel = ((string) $client->installment) === '2' ? 'Yearly' : 'Monthly';

        $history = $this->subscribe($client, round($amount, 2), $payment, $options, $unitLen, $remaining, "(Installment - {$typeLabel})");

        $client->forceFill(['paid_installment' => $installmentNo])->save();

        return $history;
    }

    private function subscribe(
        Client $client,
        float $amount,
        PaymentInput $payment,
        array $options,
        int $intervalLength,
        int $occurrences,
        string $purposeSuffix,
    ): PaymentHistory {
        $account = $this->accounts->pickForCharge() ?? $this->accounts->pickForNewClient();

        if (! $account) {
            throw new \RuntimeException('No active Authorize.Net account is configured.');
        }

        $startDate = Carbon::now()->addMonth();
        $packageTitle = $client->propertyPackage->title ?? 'Policy';
        $subscriptionName = trim(($client->first_name ?? '').' Subscription');

        return DB::transaction(function () use (
            $client, $amount, $payment, $options, $account, $startDate,
            $intervalLength, $occurrences, $purposeSuffix, $packageTitle, $subscriptionName
        ) {
            [$transactionId, $subscriptionId, $historyAccountId] = $payment->isBank()
                ? $this->chargeAndSubscribeBank($client, $amount, $payment, $account, $startDate, $intervalLength, $occurrences, $subscriptionName)
                : $this->chargeAndSubscribeCard($client, $amount, $payment, $account, $startDate, $intervalLength, $occurrences, $subscriptionName, $packageTitle);

            return $this->persistPaid(
                $client, $amount, $payment, $account, $startDate,
                $transactionId, $subscriptionId, $historyAccountId,
                trim($packageTitle.' '.$purposeSuffix), $options, backfillSubscriptionId: true,
            );
        });
    }

    /**
     * A single prepaid charge with NO ARB subscription — the white-label
     * funnel's yearly / multi-year path (legacy
     * PlanCheckoutController::yearlySubscriptionPayment). Charge-only, then
     * the same paid-history / status bookkeeping as a subscription checkout.
     */
    public function chargeOnce(Client $client, float $amount, PaymentInput $payment, array $options = []): PaymentHistory
    {
        $account = $this->accounts->pickForCharge() ?? $this->accounts->pickForNewClient();

        if (! $account) {
            throw new \RuntimeException('No active Authorize.Net account is configured.');
        }

        $startDate = Carbon::now()->addMonth();
        $packageTitle = $client->propertyPackage->title ?? 'Policy';
        $amount = round($amount, 2);

        return DB::transaction(function () use ($client, $amount, $payment, $options, $account, $startDate, $packageTitle) {
            if ($payment->isBank()) {
                $txn = $this->gateway->chargeBank($account, $client, $payment->bank, $amount);
                $transactionId = $txn['transaction_id'];
                $historyAccountId = $account->id;
            } else {
                [$profileAccount, $customerProfileId, $paymentProfileId, $historyAccountId] =
                    $this->resolveCardProfile($client, $payment, $account);

                $txn = $this->gateway->chargeProfile($profileAccount, $customerProfileId, $paymentProfileId, $amount, $packageTitle);
                $transactionId = $txn['transaction_id'];
            }

            return $this->persistPaid(
                $client, $amount, $payment, $account, $startDate,
                $transactionId, null, $historyAccountId,
                trim($packageTitle.' (prepaid)'), $options, backfillSubscriptionId: false,
            );
        });
    }

    /** Shared post-charge bookkeeping for both the subscription and prepaid paths. */
    private function persistPaid(
        Client $client,
        float $amount,
        PaymentInput $payment,
        AuthorizeAccount $account,
        Carbon $startDate,
        string $transactionId,
        ?string $subscriptionId,
        int $historyAccountId,
        string $purpose,
        array $options,
        bool $backfillSubscriptionId,
    ): PaymentHistory {
        $client->forceFill([
            'start_date' => $startDate,
            'payment_type' => $payment->paymentType !== '' ? $payment->paymentType : 0,
            'bank_info' => $payment->isBank() ? json_encode($payment->bank) : $client->bank_info,
            'status' => 1,
            'bypass_payment' => 0,
            'authorize_account_id' => $client->authorize_account_id ?: $account->id,
        ])->save();

        $history = PaymentHistory::create([
            'plan_id' => (string) time(),
            'transaction_id' => $transactionId,
            'subscription_id' => $subscriptionId,
            'client_id' => $client->id,
            'authorize_account_id' => $historyAccountId,
            'purpose' => $purpose,
            'amount' => $amount,
            'payment_date' => Carbon::now(),
            'status' => 1,
            'payment_status' => 1,
            'card_number' => $payment->last4(),
            'expMonth' => $payment->expMonth,
            'expYear' => $payment->expYear,
            'payment_type' => $payment->paymentType,
            'bank_info' => $payment->isBank() ? json_encode($payment->bank) : null,
        ]);

        if ($backfillSubscriptionId && $subscriptionId !== null) {
            // Legacy: stamp the new subscription id onto every history row for the client.
            PaymentHistory::where('client_id', $client->id)->update(['subscription_id' => $subscriptionId]);
        }

        $this->recordCommission($client, $amount);
        $this->expirePaymentLink($client, $options['payment_link_token'] ?? null);

        Log::info('Checkout completed', [
            'client_id' => $client->id, 'amount' => $amount,
            'transaction_id' => $transactionId, 'subscription_id' => $subscriptionId,
            'dry_run' => ! $this->gateway->isLive(),
        ]);

        return $history;
    }

    /** @return array{0:string,1:string,2:int} [transactionId, subscriptionId, historyAccountId] */
    private function chargeAndSubscribeBank(
        Client $client, float $amount, PaymentInput $payment, AuthorizeAccount $account,
        Carbon $startDate, int $intervalLength, int $occurrences, string $subscriptionName,
    ): array {
        $txn = $this->gateway->chargeBank($account, $client, $payment->bank, $amount);
        $sub = $this->gateway->createSubscriptionFromBank(
            $account, $client, $payment->bank, $startDate, $amount,
            $intervalLength, 'months', $occurrences,
        );

        return [$txn['transaction_id'], $sub, $account->id];
    }

    /** @return array{0:string,1:string,2:int} */
    private function chargeAndSubscribeCard(
        Client $client, float $amount, PaymentInput $payment, AuthorizeAccount $account,
        Carbon $startDate, int $intervalLength, int $occurrences, string $subscriptionName, string $packageTitle,
    ): array {
        [$profileAccount, $customerProfileId, $paymentProfileId, $historyAccountId] =
            $this->resolveCardProfile($client, $payment, $account);

        $txn = $this->gateway->chargeProfile($profileAccount, $customerProfileId, $paymentProfileId, $amount, $packageTitle);

        $sub = $this->gateway->createSubscriptionFromProfile(
            $profileAccount, $customerProfileId, $paymentProfileId, $startDate, $amount,
            $intervalLength, 'months', $occurrences, $subscriptionName,
        );

        return [$txn['transaction_id'], $sub, $historyAccountId];
    }

    /**
     * Resolve a chargeable CIM profile for the client: reuse a saved profile
     * matched on last4, else mint one from an Accept.js opaque token. The raw
     * card fallback cannot start (or stay on) a CIM profile, so it is refused
     * here exactly as the legacy funnel refused it.
     *
     * @return array{0:AuthorizeAccount,1:string,2:string,3:int} [profileAccount, customerProfileId, paymentProfileId, historyAccountId]
     */
    private function resolveCardProfile(Client $client, PaymentInput $payment, AuthorizeAccount $account): array
    {
        $existing = $this->existingCardProfile($client, $payment->last4());

        if ($existing) {
            $profileAccount = $existing->authorize_account_id
                ? (AuthorizeAccount::find($existing->authorize_account_id) ?? $account)
                : $account;

            return [
                $profileAccount,
                (string) $existing->customer_profile_id,
                (string) $existing->payment_profile_id,
                (int) ($existing->authorize_account_id ?: $account->id),
            ];
        }

        if ($payment->hasOpaqueToken()) {
            $ids = $this->gateway->createOpaqueProfile($account, [
                'first_name' => (string) $client->first_name,
                'last_name' => (string) $client->last_name,
                'email' => (string) $client->email,
                'address' => (string) $client->address,
                'city' => (string) $client->city,
                'zip' => (string) $client->zip_code,
            ], $payment->opaqueDataDescriptor, $payment->opaqueDataValue);

            CardProfile::create([
                'client_id' => $client->id,
                'authorize_account_id' => $account->id,
                'customer_profile_id' => $ids['customer_profile_id'],
                'payment_profile_id' => $ids['payment_profile_id'],
                'last4' => $payment->last4(),
                'brand' => $payment->brand ?: CardBrand::detect($payment->cardNumber),
                'expMonth' => $payment->expMonth,
                'expYear' => $payment->expYear,
                'is_default' => 0,
            ]);

            return [$account, $ids['customer_profile_id'], $ids['payment_profile_id'], $account->id];
        }

        if ($payment->hasRawCard()) {
            // Legacy charged the raw card here and then threw (money taken, no
            // profile, transaction rolled back) — that is the bug class the
            // rebuild must not reproduce, so refuse before charging.
            throw new \RuntimeException('A saved card or Accept.js token is required for a CIM checkout.');
        }

        throw new \InvalidArgumentException('No usable payment instrument supplied.');
    }

    private function existingCardProfile(Client $client, ?string $last4): ?CardProfile
    {
        $query = CardProfile::where('client_id', $client->id);

        if ($last4) {
            $query->where('last4', $last4);
        }

        return $query->latest('id')->first();
    }

    private function recordCommission(Client $client, float $amount): void
    {
        $salesman = $this->findSalesmanByName($client->salesman_name);

        if (! $salesman) {
            Log::warning('Sale commission skipped: salesman_name did not match a user.', [
                'client_id' => $client->id, 'salesman_name' => $client->salesman_name,
            ]);

            return;
        }

        if (SaleCommission::where('client_id', $client->id)->exists()) {
            return;
        }

        // Commission Settings (plan-type rate + after-hours multiplier + 2×
        // window) decide the amount; the legacy commission_double bonus is
        // layered on top exactly as before.
        $calc = app(CommissionCalculator::class)->forSale($client, $amount);
        $commission = $calc['amount'];

        if ((bool) optional(Bonus::first())->commission_double) {
            $commission *= 2;
        }

        SaleCommission::create([
            'user_id' => $salesman->id,
            'client_id' => $client->id,
            'policy_number' => $client->policy_number,
            'payment_structure' => $client->payment_structure,
            'installment' => $client->installment,
            'price' => round($amount, 2),
            'total_year' => $client->end_date && $client->start_date
                ? max(1, (int) round(Carbon::parse($client->start_date)->diffInMonths(Carbon::parse($client->end_date)) / 12))
                : null,
            'commision_price' => round($commission, 2),
            'store_date' => Carbon::now(),
        ]);
    }

    private function findSalesmanByName(?string $name): ?User
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');

        return User::query()
            ->where('type', 1)
            ->where(function ($q) use ($name, $first, $last) {
                $q->where('first_name', $name)
                    ->orWhere(fn ($q2) => $q2->where('first_name', $first)->where('last_name', $last));
            })
            ->first();
    }

    private function expirePaymentLink(Client $client, ?string $token): void
    {
        if (! $token) {
            return;
        }

        PaymentLink::where('client_id', $client->id)
            ->where('token', $token)
            ->update(['status' => 0]);
    }
}
