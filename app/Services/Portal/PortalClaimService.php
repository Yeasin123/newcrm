<?php

namespace App\Services\Portal;

use App\Models\CardProfile;
use App\Models\Claim;
use App\Models\Client;
use App\Models\PaymentHistory;
use App\Services\Billing\AuthorizeAccountSelector;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\PaymentInput;
use App\Support\CardBrand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer-portal claim filing. Faithful port of
 * Clint\ClimController::claimStore + claimOnlinePayment:
 *
 *   - the eligibility gate (active policy, no unpaid balance, in-force term,
 *     signed agreement, past the one-day waiting period) is reproduced with
 *     the legacy messages;
 *   - filing charges the customer's service-call fee (`clients.call_fee`)
 *     against a fresh CIM profile via the PaymentGateway (dry-run by
 *     default), then creates the Claim + a `claim_payment=1` PaymentHistory
 *     row and links the card profile.
 *
 * The legacy raw-SDK card handling is replaced with the Accept.js opaque
 * token flow that the rest of the rebuild uses; the ClaimIntakeWebhook
 * outbound call stays deferred (as in Phase 5).
 */
class PortalClaimService
{
    private const ELIGIBLE_AUTHORIZE_STATUSES = ['active', 'canceled', 'expired'];

    private const POLICY_EXPIRED = 'Your policy has expired. Please renew it before submitting a claim. Call: (888) 920-8280';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuthorizeAccountSelector $accounts,
    ) {}

    /**
     * @throws ValidationException when the customer may not file a claim right now
     */
    public function assertCanFileClaim(Client $client): void
    {
        $unpaid = PaymentHistory::where('client_id', $client->id)->where('payment_status', 0)->count();
        $badAuthorizeStatus = ! is_null($client->authorize_status)
            && ! in_array($client->authorize_status, self::ELIGIBLE_AUTHORIZE_STATUSES, true);

        if ($badAuthorizeStatus || $unpaid > 0) {
            throw ValidationException::withMessages(['claim' => self::POLICY_EXPIRED]);
        }

        $termExpired = false;
        if ((string) $client->payment_structure === '2' && is_null($client->agent_id)) {
            $checkDate = $client->custom_e_date ?: $client->end_date;
            $termExpired = $checkDate && Carbon::parse($checkDate)->lt(now());
        }

        if ((int) $client->status === 0 || $termExpired) {
            throw ValidationException::withMessages(['claim' => self::POLICY_EXPIRED]);
        }

        if (is_null($client->signature_date_time) && (int) $client->is_renew === 0) {
            throw ValidationException::withMessages(['claim' => __('You must sign the agreement before submitting a claim.')]);
        }

        if ((int) $client->is_renew !== 1) {
            $activeFrom = new Carbon($client->custom_s_date ?: $client->start_date);
            $eligibleFrom = $activeFrom->copy()->addDay();

            if (now()->lt($eligibleFrom)) {
                $days = (int) ceil(now()->diffInDays($eligibleFrom, absolute: true));

                throw ValidationException::withMessages([
                    'claim' => __('You can file a claim in :n :unit.', [
                        'n' => max($days, 1),
                        'unit' => $days > 1 ? __('days') : __('day'),
                    ]),
                ]);
            }
        }
    }

    /**
     * @param  array{system:mixed,issue:mixed,brand:mixed,problem:string,last_time_working_detail:string,serial_no?:?string,location?:?string,model_no?:?string}  $data
     */
    public function fileClaim(Client $client, array $data, PaymentInput $payment): Claim
    {
        $this->assertCanFileClaim($client);

        $account = $this->accounts->pickForCharge() ?? $this->accounts->pickForNewClient();

        if (! $account) {
            throw new \RuntimeException('No active Authorize.Net account is configured.');
        }

        $callFee = (float) ($client->call_fee ?: 0);

        return DB::transaction(function () use ($client, $data, $payment, $account, $callFee) {
            $ids = $this->gateway->createOpaqueProfile($account, [
                'first_name' => (string) $client->first_name,
                'last_name' => (string) $client->last_name,
                'email' => (string) $client->email,
                'address' => (string) $client->address,
                'city' => (string) $client->city,
                'zip' => (string) $client->zip_code,
            ], (string) $payment->opaqueDataDescriptor, (string) $payment->opaqueDataValue);

            $cardProfile = CardProfile::create([
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

            $txn = $this->gateway->chargeProfile(
                $account, $ids['customer_profile_id'], $ids['payment_profile_id'], $callFee, 'Service call fee',
            );

            Claim::query()->increment('serial_number');

            $claim = Claim::create([
                'client_id' => $client->id,
                'claim_no' => (string) random_int(100000, 999999),
                'system' => $data['system'] ?? 0,
                'issue' => $data['issue'] ?? 0,
                'brand' => $data['brand'] ?? 0,
                'serial_no' => $data['serial_no'] ?? null,
                'location' => $data['location'] ?? null,
                'model_no' => $data['model_no'] ?? null,
                'last_time_working_detail' => $data['last_time_working_detail'] ?? null,
                'problem' => $data['problem'],
                'status' => 1,
                'claim_status' => 2,
                'serial_number' => 1,
                'total_price' => -$callFee,
                'claim_color' => '#007BFF',
            ]);

            $latest = PaymentHistory::where('client_id', $client->id)->latest('id')->first();

            $history = PaymentHistory::create([
                'transaction_id' => $txn['transaction_id'],
                'subscription_id' => $latest->subscription_id ?? null,
                'client_id' => $client->id,
                'authorize_account_id' => $account->id,
                'claim_id' => $claim->id,
                'purpose' => 'Service call fee',
                'amount' => $callFee,
                'payment_date' => Carbon::now(),
                'status' => 1,
                'payment_status' => 1,
                'claim_payment' => 1,
                'card_number' => $payment->last4(),
                'expMonth' => $payment->expMonth,
                'expYear' => $payment->expYear,
            ]);

            $cardProfile->forceFill(['payment_histroy_id' => $history->id])->save();

            return $claim;
        });
    }
}
