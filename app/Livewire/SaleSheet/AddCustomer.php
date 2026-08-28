<?php

namespace App\Livewire\SaleSheet;

use App\Models\Addon;
use App\Models\Client;
use App\Models\LeadDetail;
use App\Models\Package;
use App\Models\PropertySize;
use App\Models\PropertyType;
use App\Services\Billing\AuthorizeAccountSelector;
use App\Services\Billing\PaymentInput;
use App\Services\SaleSheet\SalePreview;
use App\Services\SaleSheet\SaleSheetService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The Sale Sheet — staff "Add Customer / create a new home warranty policy"
 * wizard. Faithful rebuild of Admin\ClientsController::clientAdd +
 * clientAddPost: one shared client block, one or more property blocks
 * (property type / size / plan / term / services / discount), a "No Live
 * Charge" bypass, card- or bank-account payment, and a live price sidebar.
 * Submit creates the `clients` row(s) and runs one combined checkout via
 * SaleSheetService. Draft state is kept in the session.
 */
class AddCustomer extends Component
{
    private const DRAFT_KEY = 'sale_sheet_draft';

    /** @var array<string, mixed> */
    public array $client = [
        'first_name' => '', 'last_name' => '', 'email' => '', 'contact' => '',
        'salesman_name' => '', 'call_fee' => '', 'notes' => '',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $properties = [];

    public bool $noLiveCharge = false;

    public string $paymentMethod = 'card';

    // Card
    public string $expiry = '';

    public string $cardLast4 = '';

    public string $opaqueDataDescriptor = '';

    public string $opaqueDataValue = '';

    // Bank account
    public string $bankRouting = '';

    public string $bankAccount = '';

    public string $bankName = '';

    public string $bankType = 'Checking';

    public bool $draftRestored = false;

    public ?string $error = null;

    /** Set when the sheet was opened from a lead's "Convert to Client" button. */
    public ?int $fromLeadId = null;

    public string $fromLeadName = '';

    /** Set when opened from the Renewal Board's "Renew Customer — Sale Sheet". */
    public ?int $renewFromClientId = null;

    public string $renewFromName = '';

    public function mount(): void
    {
        $draft = session(self::DRAFT_KEY, []);

        // Only restore a draft that holds real progress — a client field is
        // filled or a property has an address / add-ons. (The plan is
        // pre-selected on a fresh block, so it does not count as progress.)
        $hasProgress = $draft !== [] && (
            collect($draft['client'] ?? [])->contains(fn ($v) => filled($v))
            || collect($draft['properties'] ?? [])->contains(fn ($p) => filled($p['address'] ?? null) || filled($p['addons'] ?? null))
        );

        if ($hasProgress) {
            $this->client = array_merge($this->client, $draft['client'] ?? []);
            $this->properties = array_values($draft['properties'] ?? []);
            $this->noLiveCharge = (bool) ($draft['noLiveCharge'] ?? false);
            $this->paymentMethod = $draft['paymentMethod'] ?? 'card';
            $this->draftRestored = true;
        } else {
            session()->forget(self::DRAFT_KEY);
        }

        if ($this->properties === []) {
            $this->properties = [$this->blankProperty()];
        }

        // Opened from a lead's "Convert to Client" button — pre-fill the
        // client + first property from the lead_details row (only when the
        // sheet is otherwise blank, so a restored draft is never clobbered).
        if (! $hasProgress && ($leadId = (int) request('lead')) > 0) {
            $this->prefillFromLead($leadId);
        }

        // Opened from the Renewal Board — pre-fill the client + first property
        // from the existing policy so the rep only tweaks term / add-ons.
        if (! $hasProgress && ! $this->fromLeadId && ($renewId = (int) request('renew')) > 0) {
            $this->prefillFromClient($renewId);
        }

        $this->client['call_fee'] = $this->client['call_fee'] ?: (string) config('sale_sheet.default_call_fee');

        // The sale is always attributed to the signed-in user (legacy
        // clientAdd hides a salesman_name = auth user first name field).
        $this->client['salesman_name'] = auth()->user()?->first_name ?? '';
    }

    private function prefillFromLead(int $leadId): void
    {
        $lead = LeadDetail::find($leadId);

        if (! $lead) {
            return;
        }

        $this->fromLeadId = $lead->id;
        $this->fromLeadName = trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')) ?: (string) $lead->email;

        $this->client = array_merge($this->client, array_filter([
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'contact' => $lead->contact,
            'call_fee' => filled($lead->services_call_fee) ? (string) $lead->services_call_fee : null,
        ], fn ($v) => filled($v)));

        $p = $this->properties[0];
        $p['address'] = $lead->address ?: $p['address'];
        $p['city'] = $lead->city ?: $p['city'];
        $p['state'] = $lead->state ?: $p['state'];
        $p['zip_code'] = $lead->zip_code ?: $p['zip_code'];

        if (is_numeric($lead->package) && Package::whereKey($lead->package)->exists()) {
            $p['package'] = (string) $lead->package;
        }
        if (is_numeric($lead->property_size) && PropertySize::whereKey($lead->property_size)->exists()) {
            $p['property_size'] = (int) $lead->property_size;
        }
        if (is_numeric($lead->property_type) && PropertyType::whereKey($lead->property_type)->exists()) {
            $p['property_type'] = (int) $lead->property_type;
        }
        if (is_numeric($lead->payment_terms) && isset(config('sale_sheet.terms')[(int) $lead->payment_terms])) {
            $p['payment_term'] = (int) $lead->payment_terms;
        }

        $addons = json_decode((string) $lead->addons, true);
        if (is_array($addons) && $addons !== []) {
            $p['addons'] = array_values(array_filter(array_map('intval', $addons)));
        }

        $this->properties[0] = $p;
    }

    private function prefillFromClient(int $clientId): void
    {
        $c = Client::find($clientId);

        if (! $c) {
            return;
        }

        $this->renewFromClientId = $c->id;
        $this->renewFromName = trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: (string) $c->email;

        $this->client = array_merge($this->client, array_filter([
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'email' => $c->email,
            'contact' => $c->contact,
            'call_fee' => filled($c->call_fee) ? (string) $c->call_fee : null,
        ], fn ($v) => filled($v)));

        $p = $this->properties[0];
        $p['address'] = $c->address ?: $p['address'];
        $p['city'] = $c->city ?: $p['city'];
        $p['state'] = $c->state ?: $p['state'];
        $p['zip_code'] = $c->zip_code ?: $p['zip_code'];

        if (is_numeric($c->package) && Package::whereKey($c->package)->exists()) {
            $p['package'] = (string) $c->package;
        }
        if (is_numeric($c->property_size) && PropertySize::whereKey($c->property_size)->exists()) {
            $p['property_size'] = (int) $c->property_size;
        }
        if (is_numeric($c->property_type) && PropertyType::whereKey($c->property_type)->exists()) {
            $p['property_type'] = (int) $c->property_type;
        }

        // clients.payment_structure 1 = monthly, 2 = yearly.
        $p['payment_term'] = (string) $c->payment_structure === '1' ? 1 : 2;

        $addons = json_decode((string) $c->services, true);
        if (is_array($addons) && $addons !== []) {
            $p['addons'] = array_values(array_filter(array_map('intval', $addons)));
        }

        $this->properties[0] = $p;
    }

    public function updated(string $name = ''): void
    {
        // Toggling "Apply Discount? = Yes" defaults to the first percentage;
        // "No" clears it.
        if (preg_match('/^properties\.(\d+)\.apply_discount$/', $name, $m)) {
            $i = (int) $m[1];
            $on = filter_var($this->properties[$i]['apply_discount'] ?? false, FILTER_VALIDATE_BOOL);
            $this->properties[$i]['discount_percent'] = $on
                ? ($this->properties[$i]['discount_percent'] ?: (string) (config('sale_sheet.discount_percents')[0] ?? ''))
                : '';
        }

        session([self::DRAFT_KEY => [
            'client' => $this->client,
            'properties' => $this->properties,
            'noLiveCharge' => $this->noLiveCharge,
            'paymentMethod' => $this->paymentMethod,
        ]]);
    }

    public function addProperty(): void
    {
        $this->properties[] = $this->blankProperty();
    }

    public function removeProperty(int $index): void
    {
        unset($this->properties[$index]);
        $this->properties = array_values($this->properties);

        if ($this->properties === []) {
            $this->properties = [$this->blankProperty()];
        }
    }

    public function setPropertyValue(int $index, string $key, mixed $value): void
    {
        $this->properties[$index][$key] = $value;
    }

    public function toggleAddon(int $index, int $addonId): void
    {
        $current = $this->properties[$index]['addons'] ?? [];
        $this->properties[$index]['addons'] = in_array($addonId, $current, true)
            ? array_values(array_diff($current, [$addonId]))
            : [...$current, $addonId];
    }

    public function clearDraft(): void
    {
        session()->forget(self::DRAFT_KEY);
        $this->reset('client', 'properties', 'noLiveCharge', 'paymentMethod', 'draftRestored', 'error');
        $this->mount();
    }

    #[Computed]
    public function preview(): SalePreview
    {
        return app(SaleSheetService::class)->preview($this->properties);
    }

    #[Computed]
    public function packages()
    {
        return Package::orderBy('id')->get();
    }

    #[Computed]
    public function propertySizes()
    {
        return PropertySize::orderBy('id')->get();
    }

    #[Computed]
    public function propertyTypes()
    {
        return PropertyType::orderBy('id')->get();
    }

    #[Computed]
    public function addons()
    {
        return Addon::orderBy('title')->get();
    }

    /** Real money moves only when BOTH billing flags are 'live'. */
    public function liveMode(): bool
    {
        return config('billing.driver') === 'live' && config('billing.processing_mode') === 'live';
    }

    /**
     * What the browser tokeniser needs. In dry-run these are unused — the
     * form submits a placeholder token and DryRunPaymentGateway handles it.
     *
     * @return array{live: bool, login_id: ?string, client_key: ?string, accept_js_url: string, environment: ?string}
     */
    #[Computed]
    public function anet(): array
    {
        $account = app(AuthorizeAccountSelector::class)->pickForCharge()
            ?? app(AuthorizeAccountSelector::class)->pickForNewClient();

        $isProd = $account?->environment === 'production';

        return [
            'live' => $this->liveMode(),
            'login_id' => $account?->api_login_id,
            'client_key' => $account?->public_client_key,
            'accept_js_url' => $isProd
                ? 'https://js.authorize.net/v1/Accept.js'
                : 'https://jstest.authorize.net/v1/Accept.js',
            'environment' => $account?->environment,
        ];
    }

    public function submit(SaleSheetService $sales): mixed
    {
        $rules = [
            'client.first_name' => 'required|string|max:255',
            'client.last_name' => 'required|string|max:255',
            'client.email' => 'required|email',
            'client.contact' => 'required|string|max:20',
            'client.call_fee' => 'required|numeric',
            'properties' => 'required|array|min:1',
            'properties.*.address' => 'required|string|max:255',
            'properties.*.city' => 'required|string|max:120',
            'properties.*.state' => 'required|string|max:120',
            'properties.*.zip_code' => 'required|string|max:20',
            'properties.*.property_size' => 'required',
            'properties.*.package' => 'required',
            'properties.*.payment_term' => 'required',
        ];

        if (! $this->noLiveCharge) {
            $rules += $this->paymentMethod === 'bank'
                ? [
                    'bankRouting' => 'required|string',
                    'bankAccount' => 'required|string',
                    'bankName' => 'required|string',
                    'bankType' => 'required|string',
                ]
                : [
                    'expiry' => ['required', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'],
                    'opaqueDataDescriptor' => 'required|string',
                    'opaqueDataValue' => 'required|string',
                    'cardLast4' => 'required|digits:4',
                ];
        }

        $this->validate($rules);

        try {
            $result = $sales->place($this->client, $this->properties, $this->buildPayment(), $this->noLiveCharge);
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('The sale could not be completed. Please check the payment details and try again.');

            return null;
        }

        session()->forget(self::DRAFT_KEY);

        return $this->redirectRoute('customers.show', ['customer' => $result['primary']->id], navigate: true);
    }

    private function buildPayment(): PaymentInput
    {
        if ($this->noLiveCharge) {
            return new PaymentInput;
        }

        if ($this->paymentMethod === 'bank') {
            return PaymentInput::fromArray([
                'payment_type' => '1',
                'routingNumber' => $this->bankRouting,
                'accountNumber' => $this->bankAccount,
                'bankAccountName' => $this->bankName,
                'bankAccountType' => $this->bankType,
                'echeckType' => 'WEB',
            ]);
        }

        [$month, $year] = array_pad(explode('/', str_replace(' ', '', $this->expiry)), 2, '');

        return new PaymentInput(
            paymentType: '0',
            expMonth: $month ?: null,
            expYear: $year ? (strlen($year) === 2 ? '20'.$year : $year) : null,
            opaqueDataDescriptor: $this->opaqueDataDescriptor ?: null,
            opaqueDataValue: $this->opaqueDataValue ?: null,
            explicitLast4: $this->cardLast4 ?: null,
        );
    }

    /** @return array<string, mixed> */
    private function blankProperty(): array
    {
        $defaultPackage = config('sale_sheet.default_package_id') ?: Package::orderBy('id')->value('id');

        return [
            'address' => '', 'city' => '', 'state' => '', 'zip_code' => '',
            // Default the first option selected, like "Monthly" on Payment Structure.
            'property_size' => (int) (PropertySize::orderBy('id')->value('id') ?? 0),
            'property_type' => (int) (PropertyType::orderBy('id')->value('id') ?? 0),
            'package' => (string) ($defaultPackage ?? ''),
            'addons' => [], 'payment_term' => 1, 'apply_discount' => '0', 'discount_percent' => '',
        ];
    }

    public function render(): View
    {
        return view('livewire.sale-sheet.add-customer')->layout('layouts.app', ['title' => 'Add Customer']);
    }
}
