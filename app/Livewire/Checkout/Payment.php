<?php

namespace App\Livewire\Checkout;

use App\Services\Billing\AuthorizeAccountSelector;
use App\Services\Checkout\CheckoutFunnelService;
use App\Services\Checkout\CheckoutPriceCalculator;
use App\Services\Checkout\PriceBreakdown;
use App\Services\Checkout\WlCheckoutFinalizer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The checkout payment step. Collects the billing address and an Accept.js
 * opaque token (the raw PAN is tokenised in the browser and never reaches
 * the server), then hands off to WlCheckoutFinalizer. When billing is not in
 * live mode the "place test order" path submits placeholder token values so
 * the dry-run gateway can exercise the whole flow.
 */
class Payment extends Component
{
    public ?int $packageId = null;

    public int $term = 1;

    /** @var array<int, int> */
    public array $addonIds = [];

    public ?array $promo = null;

    // Billing address
    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $expiry = '';

    // Filled by Accept.js (live) or by placeholders (dry-run).
    public string $opaqueDataDescriptor = '';

    public string $opaqueDataValue = '';

    public string $last4 = '';

    public string $cardBrand = '';

    public ?string $error = null;

    public function mount(CheckoutFunnelService $funnel): mixed
    {
        $state = $funnel->state();

        if (empty($state['lead_id']) || empty($state['package_id'])) {
            return $this->redirectRoute('shop.planes', navigate: true);
        }

        $this->packageId = (int) $state['package_id'];
        $this->term = (int) ($state['term'] ?? 1);
        $this->addonIds = array_map('intval', $state['addon_ids'] ?? []);
        $this->promo = $state['promo'] ?? null;

        return null;
    }

    #[Computed]
    public function breakdown(): ?PriceBreakdown
    {
        if ($this->packageId === null) {
            return null;
        }

        return app(CheckoutPriceCalculator::class)->calculate(
            term: $this->term,
            packageId: $this->packageId,
            addonIds: $this->addonIds,
            discountValue: $this->promo['amount'] ?? 0.0,
            discountType: isset($this->promo['type']) ? (int) $this->promo['type'] : null,
            propertySizeId: config('checkout.property_size_id'),
            propertyTypeId: config('checkout.property_type_id'),
        );
    }

    public function liveMode(): bool
    {
        return config('billing.processing_mode') === 'live' && config('billing.driver') === 'live';
    }

    /** @return array{login_id: ?string, client_key: ?string} Accept.js credentials for the live tokeniser. */
    #[Computed]
    public function anetKeys(): array
    {
        $account = app(AuthorizeAccountSelector::class)->pickForNewClient();

        return [
            'login_id' => $account?->api_login_id,
            'client_key' => $account?->public_client_key,
        ];
    }

    public function pay(WlCheckoutFinalizer $finalizer): mixed
    {
        $validated = $this->validate([
            'address' => 'required|string|max:255',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:120',
            'expiry' => ['required', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'],
            'opaqueDataDescriptor' => 'required|string',
            'opaqueDataValue' => 'required|string',
            'last4' => 'required|digits:4',
        ]);

        [$month, $year] = array_pad(explode('/', str_replace(' ', '', $validated['expiry'])), 2, '');
        $expYear = strlen($year) === 2 ? '20'.$year : $year;

        try {
            $finalizer->finalize([
                'address' => $validated['address'],
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'expMonth' => $month,
                'expYear' => $expYear,
                'opaqueDataDescriptor' => $validated['opaqueDataDescriptor'],
                'opaqueDataValue' => $validated['opaqueDataValue'],
                'last4' => $validated['last4'],
                'brand' => $this->cardBrand ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('We could not process your payment. Please check your card details and try again.');

            return null;
        }

        return $this->redirectRoute('thankyou', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.checkout.payment')->layout('layouts.public', ['title' => __('Payment')]);
    }
}
