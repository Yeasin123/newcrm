<?php

namespace App\Services\Checkout;

use App\Mail\PolicyNumberMail;
use App\Models\Client;
use App\Models\WlInquiry;
use App\Services\Billing\AuthorizeAccountSelector;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Turns a completed funnel session + the payment form into a live policy.
 * Faithful consolidation of PlanCheckoutController::checkoutstepFinal +
 * storeData:
 *
 *   - the charged amount is recomputed server-side from the funnel state
 *     (the browser-supplied total is never trusted);
 *   - a `clients` row is created (client_type = WL, promo_code,
 *     price_cal_response, prepaid end_date for multi-year terms);
 *   - monthly (term 1) => CIM charge + ARB via CheckoutService::monthly;
 *     1/3/5-year (terms 2/4/6) => a single prepaid CIM charge, no ARB, via
 *     CheckoutService::chargeOnce;
 *   - the lead / inquiry / wl_inquiry rows are advanced, the welcome email
 *     with the policy number goes out, and the funnel session is cleared.
 */
class WlCheckoutFinalizer
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CheckoutPriceCalculator $pricing,
        private readonly CheckoutFunnelService $funnel,
        private readonly AuthorizeAccountSelector $accounts,
    ) {}

    /**
     * @param  array{first_name?:string,last_name?:string,email?:string,contact?:string,address?:string,city?:string,state?:string,expMonth?:string,expYear?:string,opaqueDataDescriptor?:string,opaqueDataValue?:string,last4?:string,brand?:string}  $form
     */
    public function finalize(array $form): Client
    {
        $state = $this->funnel->state();
        $packageId = (int) ($state['package_id'] ?? 0);

        if ($packageId === 0 || empty($state['lead_id'])) {
            throw new \RuntimeException('checkout_session_missing');
        }

        $term = (int) ($state['term'] ?? 1);
        $terms = config('checkout.terms');
        $termCfg = $terms[$term] ?? $terms[1];
        $addonIds = array_map('intval', $state['addon_ids'] ?? []);
        $promo = $state['promo'] ?? null;

        $breakdown = $this->pricing->calculate(
            term: $term,
            packageId: $packageId,
            addonIds: $addonIds,
            discountValue: $promo['amount'] ?? 0.0,
            discountType: isset($promo['type']) ? (int) $promo['type'] : null,
            propertySizeId: config('checkout.property_size_id'),
            propertyTypeId: config('checkout.property_type_id'),
        );

        $policyNumber = sprintf('%d-%d-%d', random_int(100, 999), random_int(100, 999), random_int(100, 999));
        $account = $this->accounts->pickForCharge() ?? $this->accounts->pickForNewClient();
        $prepaidMonths = config('checkout.prepaid_end_months');

        $client = DB::transaction(function () use ($state, $form, $breakdown, $term, $termCfg, $addonIds, $promo, $packageId, $policyNumber, $account, $prepaidMonths) {
            $client = Client::create([
                'policy_number' => $policyNumber,
                'password' => Hash::make($policyNumber),
                'first_name' => $state['name']['first_name'] ?? ($form['first_name'] ?? null),
                'last_name' => $state['name']['last_name'] ?? ($form['last_name'] ?? null),
                'email' => $state['email'] ?? ($form['email'] ?? null),
                'contact' => $this->funnel->normalizePhone((string) ($form['contact'] ?? $state['contact'] ?? '')),
                'address' => $form['address'] ?? null,
                'city' => $form['city'] ?? null,
                'state' => $form['state'] ?? null,
                'zip_code' => $state['zip_code'] ?? null,
                'payment_structure' => (string) $termCfg['structure'],
                'property_size' => config('checkout.property_size_id'),
                'property_type' => config('checkout.property_type_id'),
                'package' => $packageId,
                'services' => $addonIds ? json_encode($addonIds) : null,
                'discount' => ! empty($promo['amount']) ? $promo['amount'] : null,
                'promo_code' => $promo['name'] ?? null,
                'bypass_payment' => 0,
                'call_fee' => config('checkout.call_fee'),
                'client_type' => config('checkout.client_type'),
                'authorize_account_id' => $account?->id,
                'price_cal_response' => json_encode($breakdown->toLegacyArray()),
                'start_date' => Carbon::now(),
                'end_date' => isset($prepaidMonths[$term])
                    ? Carbon::now()->addMonths($prepaidMonths[$term])
                    : null,
                'status' => 0,
            ]);

            $payment = new PaymentInput(
                paymentType: '0',
                expMonth: $form['expMonth'] ?? null,
                expYear: $form['expYear'] ?? null,
                opaqueDataDescriptor: $form['opaqueDataDescriptor'] ?? null,
                opaqueDataValue: $form['opaqueDataValue'] ?? null,
                explicitLast4: $form['last4'] ?? null,
                brand: $form['brand'] ?? null,
            );

            $termCfg['recurring']
                ? $this->checkout->monthly($client, $breakdown->payable, $payment)
                : $this->checkout->chargeOnce($client, $breakdown->payable, $payment);

            return $client->refresh();
        });

        if ($lead = $this->funnel->currentLead()) {
            $lead->update(['status' => 1, 'progress_bar' => 5]);
        }
        if ($inquiry = $this->funnel->currentInquiry()) {
            $inquiry->update(['progress_bar' => 5]);
        }
        if ($zip = $state['zip_code'] ?? null) {
            WlInquiry::where('zip_code', $zip)->update([
                'step' => 8,
                'address' => $form['address'] ?? null,
                'payment_term' => $term,
                'promo_name' => $promo['name'] ?? null,
            ]);
        }

        if ($client->email) {
            Mail::to($client->email)->send(new PolicyNumberMail($client));
        }

        $this->funnel->forget();

        return $client;
    }
}
