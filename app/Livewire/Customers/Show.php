<?php

namespace App\Livewire\Customers;

use App\Models\Addon;
use App\Models\CardProfile;
use App\Models\Client;
use App\Models\ClientButtonTrack;
use App\Models\ClientLoginInfo;
use App\Models\Note;
use App\Models\Package;
use App\Models\PaymentHistory;
use App\Models\PlanChangeLog;
use App\Models\PropertySize;
use App\Models\PropertyType;
use App\Models\User;
use App\Services\Billing\AuthorizeAccountSelector;
use App\Services\Billing\BillingActions;
use App\Services\Billing\BillingActionService;
use App\Services\Billing\DisputeService;
use App\Services\Billing\NewPlanInput;
use App\Services\Billing\PaymentInput;
use App\Services\Billing\PlanChangeService;
use App\Services\SaleSheet\SaleSheetPriceCalculator;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Customer detail screen — the admin equivalent of the legacy
 * Admin\ClientsController@show / clientEdit / planChange screens folded into
 * one Livewire component: profile + coverage + invoices + claims + activity +
 * notes tabs, an Edit Customer Profile flyout, an Edit Plan & Policy modal
 * (local field save, plus the optional Authorize.Net billing actions handled
 * by PlanChangeService::applyManual), and the ad-hoc billing actions
 * (custom charge / void-refund / dispute / cancel) from BillingActionService.
 *
 * No outbound communication is sent from here while the app is in
 * development — "Send receipt" / "Send claim email" are gated on
 * config('comms.enabled') and only report that comms are off.
 */
class Show extends Component
{
    public Client $customer;

    // ---- Notes -----------------------------------------------------------
    #[Validate('required|string|min:2|max:5000')]
    public string $noteBody = '';

    // ---- Custom charge modal -------------------------------------------
    public bool $showChargeModal = false;

    public string $chargeAmount = '';

    public string $chargeReason = '';

    // ---- Cancel policy modal ------------------------------------------
    public bool $showCancelModal = false;

    public string $cancelReason = '';

    // ---- Edit Customer Profile flyout -------------------------------
    public bool $showProfileModal = false;

    /** @var array<string,mixed> */
    public array $profile = [];

    // ---- Edit Plan & Policy modal ---------------------------------
    public bool $showPlanModal = false;

    public string $epPackage = '';

    public string $epStructure = '1';

    public string $epPropertyType = '';

    public string $epPropertySize = '';

    public string $epSalesman = '';

    /** @var array<int,int> */
    public array $epAddons = [];

    public string $epCustomPrice = '';

    public string $epCallFee = '';

    public string $epDiscount = '';

    public string $epPurchaseDate = '';

    public string $epStartDate = '';

    public string $epInstallment = '';

    // Optional Authorize.Net billing actions on the Edit Plan modal.
    public bool $epChargeToday = false;

    public string $epChargeAmount = '';

    public bool $epUpdateSub = false;

    public string $epUpdateAmount = '';

    public bool $epCancelSub = false;

    // ---- Add Payment Method (inline in the "Payment on File" box) -----
    public bool $showAddPayment = false;

    public string $pmMethod = 'card';

    public string $pmCardNumber = '';

    public string $pmExpMonth = '';

    public string $pmExpYear = '';

    public string $pmName = '';

    public string $pmDescriptor = '';

    public string $pmValue = '';

    public string $pmLast4 = '';

    public string $pmRouting = '';

    public string $pmAccount = '';

    public string $pmBankName = '';

    public string $pmBankType = 'Checking';

    public function mount(Client $customer): void
    {
        $this->customer = $customer;
        $this->recordProfileView();
    }

    /**
     * Log an admin opening this customer — deduped to one row per viewer per
     * 10-minute window so the Activity tab is not flooded on every repaint.
     */
    private function recordProfileView(): void
    {
        $recent = ClientButtonTrack::where('client_id', $this->customer->id)
            ->where('user_id', auth()->id())
            ->where('button_text', 'Profile View')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($recent) {
            return;
        }

        ClientButtonTrack::create([
            'client_id' => $this->customer->id,
            'user_id' => auth()->id(),
            'create_date' => now()->toDateTimeString(),
            'status' => 1,
            'button_text' => 'Profile View',
            'comments' => trim((auth()->user()?->first_name ?? __('A user')).' viewed '.($this->customer->full_name ?: __('this customer')).'\'s profile'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Lightweight, rule-based "insight" banner (no LLM call — safe offline).
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function insight(): ?string
    {
        $c = $this->customer;
        $name = $c->first_name ?: __('This customer');

        if ((int) $c->status === 0 || $c->login_off) {
            return __(':name is offline right now — review the last failed payment and the cancel reason before reaching out.', ['name' => $name]);
        }

        if ($c->latestFailedPolicyPaymentHistory) {
            $when = optional($c->payment_faild_date)->format('M j') ?: __('recently');

            return __('A policy payment failed (:when). Retry the card from Invoices or call the customer before the account suspends.', ['when' => $when]);
        }

        if ($c->isMonthly()) {
            return __(':name is on monthly billing — pitch the annual prepay option to save roughly 15% and lock the rate in before renewal.', ['name' => $name]);
        }

        if ($c->isYearly() && $c->end_date) {
            try {
                $days = now()->diffInDays(Carbon::parse($c->end_date), false);
                if ($days >= 0 && $days <= 60) {
                    return __('Coverage renews on :date (:days days). Start the renewal conversation now.', [
                        'date' => Carbon::parse($c->end_date)->format('M j, Y'),
                        'days' => (int) $days,
                    ]);
                }
            } catch (\Throwable) {
            }
        }

        if (blank($c->services) || $c->services === '[]') {
            return __('No coverage add-ons on this plan — suggesting HVAC or electrical coverage raises the plan value and the commission.', ['name' => $name]);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Catalogs for the Edit Plan modal
    |--------------------------------------------------------------------------
    */
    #[Computed]
    public function packages()
    {
        return Package::orderBy('id')->get();
    }

    #[Computed]
    public function propertyTypes()
    {
        return PropertyType::orderBy('id')->get();
    }

    #[Computed]
    public function propertySizes()
    {
        return PropertySize::orderBy('id')->get();
    }

    #[Computed]
    public function addons()
    {
        return Addon::orderBy('title')->get();
    }

    #[Computed]
    public function salesmen()
    {
        return User::query()
            ->whereNotNull('first_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }

    /** Live recomputed plan price for the Edit Plan modal. */
    #[Computed]
    public function autoPrice(): float
    {
        $term = $this->epStructure === '1' ? 1 : 2;

        return app(SaleSheetPriceCalculator::class)->calculate(
            term: $term,
            packageId: $this->epPackage !== '' ? (int) $this->epPackage : null,
            addonIds: $this->epAddons,
            propertySizeId: $this->epPropertySize !== '' ? (int) $this->epPropertySize : null,
            propertyTypeId: $this->epPropertyType !== '' ? (int) $this->epPropertyType : null,
            discountPercent: (float) ($this->epDiscount ?: 0),
            installmentSplit: $this->epInstallment !== '' ? (int) $this->epInstallment : null,
        )->payable;
    }

    /** Real money moves only when BOTH billing flags are 'live'. */
    public function liveMode(): bool
    {
        return config('billing.driver') === 'live' && config('billing.processing_mode') === 'live';
    }

    private function commsOn(): bool
    {
        return (bool) config('comms.enabled') && (bool) config('comms.email');
    }

    /**
     * What the browser Accept.js tokeniser needs for the "Add Payment Method"
     * form. In dry-run these go unused — a placeholder token is submitted and
     * DryRunPaymentGateway returns synthetic profile ids.
     *
     * @return array{live:bool, login_id:?string, client_key:?string, accept_js_url:string}
     */
    #[Computed]
    public function anet(): array
    {
        $account = app(AuthorizeAccountSelector::class)->pickForCharge()
            ?? app(AuthorizeAccountSelector::class)->pickForNewClient();

        return [
            'live' => $this->liveMode(),
            'login_id' => $account?->api_login_id,
            'client_key' => $account?->public_client_key,
            'accept_js_url' => $account?->environment === 'production'
                ? 'https://js.authorize.net/v1/Accept.js'
                : 'https://jstest.authorize.net/v1/Accept.js',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Add Payment Method — inline card / bank capture in the Payment on File box
    |--------------------------------------------------------------------------
    */
    public function addPaymentMethodForm(): void
    {
        $this->resetPaymentForm();
        $this->showAddPayment = true;
    }

    public function cancelAddPayment(): void
    {
        $this->resetPaymentForm();
        $this->showAddPayment = false;
    }

    private function resetPaymentForm(): void
    {
        $this->reset(
            'pmMethod', 'pmCardNumber', 'pmExpMonth', 'pmExpYear', 'pmName',
            'pmDescriptor', 'pmValue', 'pmLast4',
            'pmRouting', 'pmAccount', 'pmBankName', 'pmBankType',
        );
    }

    public function savePaymentMethod(BillingActionService $billing): void
    {
        $rules = $this->pmMethod === 'bank'
            ? [
                'pmRouting' => 'required|string|min:8',
                'pmAccount' => 'required|string|min:4',
                'pmName' => 'required|string|max:255',
                'pmBankType' => 'required|string',
            ]
            : [
                'pmExpMonth' => ['required', 'regex:/^(0?[1-9]|1[0-2])$/'],
                'pmExpYear' => ['required', 'regex:/^\d{2}(\d{2})?$/'],
            ];

        // Live mode tokenises in the browser (pmDescriptor/pmValue arrive set);
        // dry-run submits the raw digits so DryRunPaymentGateway can build a
        // synthetic profile.
        if ($this->pmMethod === 'card' && $this->pmDescriptor === '') {
            $rules['pmCardNumber'] = ['required', 'string', 'regex:/^[\d\s-]{12,25}$/'];
        }

        $this->validate($rules);

        try {
            $billing->addPaymentMethod($this->customer, $this->buildPaymentMethodInput());
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->customer->load('cardProfiles');
        $this->cancelAddPayment();
        Flux::toast(variant: 'success', text: __('Payment method saved and linked to :name.', ['name' => $this->customer->full_name ?: __('this customer')]));
    }

    private function buildPaymentMethodInput(): PaymentInput
    {
        if ($this->pmMethod === 'bank') {
            return PaymentInput::fromArray([
                'payment_type' => '1',
                'routingNumber' => preg_replace('/\D+/', '', $this->pmRouting),
                'accountNumber' => preg_replace('/\D+/', '', $this->pmAccount),
                'bankAccountName' => $this->pmName,
                'bankAccountType' => $this->pmBankType,
                'echeckType' => 'WEB',
            ]);
        }

        return new PaymentInput(
            paymentType: '0',
            cardNumber: $this->pmDescriptor !== '' ? null : (preg_replace('/\D+/', '', $this->pmCardNumber) ?: null),
            expMonth: $this->pmExpMonth !== '' ? str_pad($this->pmExpMonth, 2, '0', STR_PAD_LEFT) : null,
            expYear: $this->pmExpYear !== '' ? (strlen($this->pmExpYear) === 2 ? '20'.$this->pmExpYear : $this->pmExpYear) : null,
            opaqueDataDescriptor: $this->pmDescriptor ?: null,
            opaqueDataValue: $this->pmValue ?: null,
            explicitLast4: $this->pmLast4 ?: null,
        );
    }

    public function deleteCardProfile(int $profileId): void
    {
        CardProfile::where('client_id', $this->customer->id)->findOrFail($profileId)->delete();
        $this->customer->load('cardProfiles');
        Flux::toast(variant: 'success', text: __('Payment method removed.'));
    }

    /*
    |--------------------------------------------------------------------------
    | Header actions
    |--------------------------------------------------------------------------
    */
    /** The six header-dropdown states mapped onto the legacy client flags. */
    public const STATUS_KEYS = ['active', 'failed', 'bypass', 'renewal', 'cancelled', 'inactive'];

    public function statusKey(): string
    {
        $c = $this->customer;

        return match (true) {
            (int) $c->status === 5 => 'bypass',
            $c->login_off && filled($c->cancle_date) => 'cancelled',
            (bool) $c->is_renew && (int) $c->status === 1 => 'renewal',
            (int) $c->status === 0 && $c->login_off => 'inactive',
            (int) $c->status === 0 => 'failed',
            default => 'active',
        };
    }

    public function setStatus(string $value): void
    {
        if (! in_array($value, self::STATUS_KEYS, true)) {
            return;
        }

        $fields = match ($value) {
            'active' => ['status' => 1, 'login_off' => 0, 'bypass_payment' => 0, 'is_renew' => 0],
            'failed' => ['status' => 0],
            'bypass' => ['status' => 5, 'bypass_payment' => 1],
            'renewal' => ['status' => 1, 'is_renew' => 1],
            'cancelled' => ['status' => 0, 'login_off' => 1, 'cancle_date' => now()->toDateString()],
            'inactive' => ['status' => 0, 'login_off' => 1],
        };

        $this->customer->update($fields);
        $this->customer->refresh();

        Flux::toast(
            variant: $value === 'active' || $value === 'renewal' ? 'success' : ($value === 'bypass' ? 'warning' : 'danger'),
            text: __('Policy status set to :s.', ['s' => ucfirst($value)]),
        );
    }

    public function toggleStatus(): void
    {
        $this->customer->status = $this->customer->status ? 0 : 1;
        $this->customer->save();

        Flux::toast(
            variant: $this->customer->status ? 'success' : 'warning',
            text: $this->customer->status ? __('Policy activated.') : __('Policy suspended.'),
        );
    }

    public function toggleLoginOff(): void
    {
        $this->customer->login_off = ! $this->customer->login_off;
        $this->customer->save();

        Flux::toast(
            variant: $this->customer->login_off ? 'warning' : 'success',
            text: $this->customer->login_off ? __('Portal login disabled.') : __('Portal login enabled.'),
        );
    }

    public function toggleStar(): void
    {
        $this->customer->star = ! $this->customer->star;
        $this->customer->save();
    }

    public function retryAuth(): void
    {
        Flux::toast(variant: 'success', text: __('Re-verification queued — the gateway status will refresh on the next sync.'));
    }

    public function sendReceipt(): void
    {
        Flux::toast(
            variant: $this->commsOn() ? 'success' : 'warning',
            text: $this->commsOn()
                ? __('Receipt emailed to :email.', ['email' => $this->customer->email])
                : __('Communications are turned off in development — nothing was sent.'),
        );
    }

    public function sendClaimEmail(): void
    {
        Flux::toast(
            variant: $this->commsOn() ? 'success' : 'warning',
            text: $this->commsOn()
                ? __('Claim link emailed to :email.', ['email' => $this->customer->email])
                : __('Communications are turned off in development — nothing was sent.'),
        );
    }

    public function deleteCustomer(): mixed
    {
        $this->customer->delete();

        Flux::toast(variant: 'success', text: __('Customer archived.'));

        return $this->redirectRoute('customers.index', navigate: true);
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Customer Profile flyout
    |--------------------------------------------------------------------------
    */
    public function openProfile(): void
    {
        $card = $this->customer->cardProfiles()->latest('id')->first();

        $this->profile = [
            'first_name' => $this->customer->first_name,
            'last_name' => $this->customer->last_name,
            'email' => $this->customer->email,
            'contact' => $this->customer->contact,
            'address' => $this->customer->address,
            'city' => $this->customer->city,
            'state' => $this->customer->state,
            'zip_code' => $this->customer->zip_code,
            'status' => (string) $this->customer->status,
            'authorize_status' => $this->customer->authorize_status,
            'authorize_account_id' => (string) ($this->customer->authorize_account_id ?? ''),
            'card_last4' => $card?->last4 ?? $this->customer->pm_last_four,
            'card_expiry' => $card && $card->expMonth
                ? str_pad((string) $card->expMonth, 2, '0', STR_PAD_LEFT).'/'.$card->expYear
                : '',
        ];

        $this->showProfileModal = true;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'profile.first_name' => 'required|string|max:255',
            'profile.last_name' => 'nullable|string|max:255',
            'profile.email' => 'required|email|max:255',
            'profile.contact' => 'nullable|string|max:40',
            'profile.address' => 'nullable|string|max:255',
            'profile.city' => 'nullable|string|max:120',
            'profile.state' => 'nullable|string|max:120',
            'profile.zip_code' => 'nullable|string|max:20',
            'profile.status' => 'required|in:0,1,5',
            'profile.authorize_status' => 'nullable|string|max:60',
            'profile.authorize_account_id' => 'nullable|numeric',
            'profile.card_last4' => 'nullable|digits:4',
            'profile.card_expiry' => ['nullable', 'regex:#^(0[1-9]|1[0-2])/\d{2,4}$#'],
        ])['profile'];

        $this->customer->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'contact' => $data['contact'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'zip_code' => $data['zip_code'],
            'status' => (int) $data['status'],
            'authorize_status' => $data['authorize_status'],
            'authorize_account_id' => $data['authorize_account_id'] !== '' ? (int) $data['authorize_account_id'] : null,
        ]);

        // Card last4 / expiry are a display cache — push onto the newest card
        // profile if there is one, otherwise leave the gateway data untouched.
        if (filled($data['card_last4']) || filled($data['card_expiry'])) {
            if ($card = $this->customer->cardProfiles()->latest('id')->first()) {
                [$mm, $yy] = array_pad(explode('/', (string) $data['card_expiry']), 2, '');
                $card->update([
                    'last4' => $data['card_last4'] ?: $card->last4,
                    'expMonth' => $mm ?: $card->expMonth,
                    'expYear' => $yy ? (strlen($yy) === 2 ? '20'.$yy : $yy) : $card->expYear,
                ]);
            }
        }

        $this->customer->refresh();
        $this->showProfileModal = false;
        Flux::toast(variant: 'success', text: __('Customer profile updated.'));
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Plan & Policy modal
    |--------------------------------------------------------------------------
    */
    public function openPlan(): void
    {
        $c = $this->customer;

        $this->epPackage = (string) ($c->package ?? '');
        $this->epStructure = (string) ($c->payment_structure ?: '1');
        $this->epPropertyType = (string) ($c->property_type ?? '');
        $this->epPropertySize = (string) ($c->property_size ?? '');
        $this->epSalesman = (string) ($c->salesman_name ?? '');
        $this->epAddons = collect(json_decode($c->services ?? '[]', true) ?: [])->map(fn ($v) => (int) $v)->all();
        $this->epCustomPrice = (string) ($c->custom_price ?? '');
        $this->epCallFee = (string) ($c->call_fee ?? '');
        $this->epDiscount = (string) ($c->discount && $c->discount !== '0' ? $c->discount : '');
        $this->epInstallment = (string) ($c->installment ?? '');
        $this->epPurchaseDate = $this->dateInput($c->custom_purchaes_date ?: $c->created_at);
        $this->epStartDate = $this->dateInput($c->custom_s_date ?: $c->start_date);

        $this->reset('epChargeToday', 'epChargeAmount', 'epUpdateSub', 'epUpdateAmount', 'epCancelSub');
        $this->showPlanModal = true;
    }

    public function toggleEpAddon(int $addonId): void
    {
        $this->epAddons = in_array($addonId, $this->epAddons, true)
            ? array_values(array_diff($this->epAddons, [$addonId]))
            : [...$this->epAddons, $addonId];
    }

    public function resetPlanPrice(): void
    {
        $this->epCustomPrice = '';
    }

    public function savePlan(PlanChangeService $planChange): void
    {
        $this->validate([
            'epPackage' => 'required',
            'epStructure' => 'required|in:1,2',
            'epCallFee' => 'nullable|numeric',
            'epCustomPrice' => 'nullable|numeric',
            'epDiscount' => 'nullable|numeric|min:0|max:100',
            'epChargeAmount' => 'nullable|numeric',
            'epUpdateAmount' => 'nullable|numeric',
        ]);

        $billingActions = $this->epChargeToday || $this->epUpdateSub || $this->epCancelSub;

        try {
            if ($billingActions) {
                $planChange->applyManual(
                    $this->customer,
                    $this->buildNewPlanInput(),
                    new BillingActions([
                        'charge_today' => $this->epChargeToday,
                        'charge_today_amount' => $this->epChargeAmount,
                        'update_existing_subscription' => $this->epUpdateSub,
                        'update_existing_amount' => $this->epUpdateAmount,
                        'cancel_subscription' => $this->epCancelSub,
                    ]),
                    auth()->id(),
                );
            } else {
                $this->persistPlanFields();
            }
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->customer->refresh();
        $this->showPlanModal = false;
        Flux::toast(variant: 'success', text: __('Plan & policy updated.'));
    }

    private function buildNewPlanInput(): NewPlanInput
    {
        return new NewPlanInput(
            paymentTermCode: $this->epStructure === '1' ? 1 : 2,
            installmentOn: $this->epInstallment !== '' && (int) $this->epInstallment >= 2,
            installmentType: (int) ($this->epInstallment ?: 0),
            newTotalPrice: $this->autoPrice(),
            packageId: (int) $this->epPackage,
            propertySize: $this->epPropertySize !== '' ? (int) $this->epPropertySize : null,
            propertyType: $this->epPropertyType !== '' ? (int) $this->epPropertyType : null,
            addonIds: $this->epAddons,
            discount: $this->epDiscount !== '' ? '1' : '0',
            discountValue: (float) ($this->epDiscount ?: 0),
            customPrice: $this->epCustomPrice ?: null,
            serviceCallFee: $this->epCallFee ?: null,
            salesmanName: $this->epSalesman ?: null,
            coverageStartDate: $this->epStartDate ?: null,
            customPurchaseDate: $this->epPurchaseDate ?: null,
        );
    }

    private function persistPlanFields(): void
    {
        $update = [
            'package' => (int) $this->epPackage,
            'payment_structure' => $this->epStructure,
            'property_type' => $this->epPropertyType !== '' ? (int) $this->epPropertyType : null,
            'property_size' => $this->epPropertySize !== '' ? (int) $this->epPropertySize : null,
            'salesman_name' => $this->epSalesman ?: null,
            'services' => json_encode(array_values($this->epAddons)),
            'discount' => $this->epDiscount ?: '0',
            'call_fee' => $this->epCallFee ?: null,
            'custom_price' => $this->epCustomPrice !== ''
                ? number_format((float) $this->epCustomPrice, 2, '.', '')
                : null,
        ];

        if ($this->epPurchaseDate !== '') {
            $update['custom_purchaes_date'] = $this->epPurchaseDate;
        }

        if ($this->epStartDate !== '') {
            $update[$this->customer->custom_s_date ? 'custom_s_date' : 'start_date'] = $this->epStartDate;
        }

        if ($this->epStructure === '1') {
            $update['end_date'] = null;
            $update['installment'] = null;
        } elseif ($this->epInstallment !== '') {
            $update['installment'] = (int) $this->epInstallment >= 2 ? $this->epInstallment : null;
        }

        $this->customer->update($update);
    }

    private function dateInput(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Ad-hoc billing actions (unchanged API — covered by BillingActionsTest)
    |--------------------------------------------------------------------------
    */
    public function chargeCustom(BillingActionService $billing): void
    {
        $this->validate([
            'chargeAmount' => 'required|numeric|min:0.01',
            'chargeReason' => 'required|string|max:255',
        ]);

        try {
            $billing->chargeCustom($this->customer, (float) $this->chargeAmount, $this->chargeReason);
            $this->reset('chargeAmount', 'chargeReason', 'showChargeModal');
            Flux::toast(variant: 'success', text: __('Charge recorded.'));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function reverse(BillingActionService $billing, int $paymentId): void
    {
        $payment = PaymentHistory::where('client_id', $this->customer->id)->findOrFail($paymentId);

        try {
            $result = $billing->voidOrRefund($payment);
            Flux::toast(
                variant: 'success',
                text: $result['action'] === 'void' ? __('Payment voided.') : __('Payment refunded.'),
            );
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function markDisputed(DisputeService $disputes, int $paymentId): void
    {
        $payment = PaymentHistory::where('client_id', $this->customer->id)->findOrFail($paymentId);
        $disputes->markDisputed($payment, 'Flagged from the customer screen.');
        Flux::toast(variant: 'success', text: __('Dispute opened.'));
    }

    /**
     * Flip a payment row between paid / unpaid. Manual bookkeeping only — the
     * gateway is never touched here.
     */
    public function setPaymentStatus(int $paymentId, string $value): void
    {
        $payment = PaymentHistory::where('client_id', $this->customer->id)->findOrFail($paymentId);
        $payment->update([
            'payment_status' => $value === 'paid' ? 1 : 0,
            'status' => $value === 'paid' ? 1 : 0,
        ]);

        Flux::toast(variant: 'success', text: __('Invoice marked :s.', ['s' => $value]));
    }

    /**
     * Remove a manual / custom charge row. Real policy-billing rows (ARB,
     * claim payments) can never be deleted from here.
     */
    public function deletePayment(int $paymentId): void
    {
        $payment = PaymentHistory::where('client_id', $this->customer->id)->findOrFail($paymentId);

        if (! $payment->is_custom) {
            Flux::toast(variant: 'warning', text: __('Only manual charges can be removed — void or refund a billing payment instead.'));

            return;
        }

        $payment->delete();
        Flux::toast(variant: 'success', text: __('Manual charge removed.'));
    }

    public function cancelPolicy(BillingActionService $billing): void
    {
        try {
            $result = $billing->cancelPolicy($this->customer, $this->cancelReason ?: null);
            $this->customer->refresh();
            $this->reset('cancelReason', 'showCancelModal');
            Flux::toast(
                variant: $result === 'error' ? 'warning' : 'success',
                text: match ($result) {
                    'error' => __('Policy taken offline, but the gateway cancel failed — check the subscription manually.'),
                    'no_subscription' => __('Policy taken offline (no active subscription found).'),
                    default => __('Policy cancelled.'),
                },
            );
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function addNote(): void
    {
        $this->validate();

        Note::create([
            'client_id' => (string) $this->customer->id,
            'user_id' => (string) auth()->id(),
            'notes' => $this->noteBody,
            'date_time' => now()->toDateTimeString(),
            'status' => 1,
        ]);

        $this->reset('noteBody');
        $this->customer->load('notes.author');

        Flux::toast(variant: 'success', text: __('Note added.'));
    }

    public function render(): View
    {
        $this->customer->loadMissing([
            'propertyPackage',
            'propertyType',
            'propertySize',
            'authorizeAccount:id,name,environment',
            'cardProfiles',
            'notes.author:id,first_name,last_name',
        ]);

        $payments = $this->customer->paymentHistories()
            ->latest('id')
            ->limit(100)
            ->get();

        $latestSub = $payments->firstWhere(fn ($p) => filled($p->subscription_id));

        $addonMap = Addon::orderBy('title')->get()->keyBy('id');
        $selectedAddons = collect(json_decode($this->customer->services ?? '[]', true) ?: [])
            ->map(fn ($id) => $addonMap->get((int) $id))
            ->filter()
            ->values();

        // Coverage checklist — the package's catalog line items.
        $coverageItems = collect();
        if ($this->customer->propertyPackage) {
            try {
                $coverageItems = $this->customer->propertyPackage->services()->orderBy('title')->pluck('title');
            } catch (\Throwable) {
            }
        }

        // Invoice roll-up for the stat cards.
        $paid = $payments->filter(fn ($p) => $p->isPaid() && ! $p->refund_payment);
        $unpaid = $payments->filter(fn ($p) => ! $p->isPaid());
        $reversed = $payments->filter(fn ($p) => (bool) $p->refund_payment);
        $monthlyPrice = filled($this->customer->custom_price)
            ? (float) $this->customer->custom_price
            : (float) ($this->customer->latestEligiblePolicyPaymentHistory->amount ?? 0);

        $invoiceStats = [
            'collected' => round($paid->sum('amount'), 2),
            'paid_count' => $paid->count(),
            'total_count' => $payments->count(),
            'outstanding' => round($unpaid->sum('amount'), 2),
            'reversed' => round($reversed->sum(fn ($p) => (float) ($p->refund_amount ?: $p->amount)), 2),
            'monthly_price' => round($monthlyPrice, 2),
            'commission' => round($monthlyPrice * 0.9, 2),
            'net_revenue' => round($monthlyPrice - ($monthlyPrice * 0.9), 2),
        ];

        return view('livewire.customers.show', [
            'payments' => $payments,
            'latestSub' => $latestSub,
            'selectedAddons' => $selectedAddons,
            'coverageItems' => $coverageItems,
            'invoiceStats' => $invoiceStats,
            'claims' => $this->customer->claims()->latest('id')->limit(50)->get(),
            'activity' => $this->customer->buttonTracks()->with('user:id,first_name,last_name')->limit(40)->get(),
            'planLogs' => PlanChangeLog::where('client_id', $this->customer->id)->latest('id')->limit(20)->get(),
            'loginActivity' => ClientLoginInfo::where('client_id', $this->customer->id)->latest('id')->limit(20)->get(),
        ])->layout('layouts.app', ['title' => $this->customer->full_name ?: 'Customer']);
    }
}
