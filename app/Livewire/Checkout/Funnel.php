<?php

namespace App\Livewire\Checkout;

use App\Models\Addon;
use App\Models\Package;
use App\Services\Checkout\CheckoutFunnelService;
use App\Services\Checkout\CheckoutPriceCalculator;
use App\Services\Checkout\PriceBreakdown;
use App\Services\Checkout\PromoCodeService;
use App\Services\Checkout\PromoResult;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The public plan-purchase funnel — one modern Livewire wizard in place of
 * the three legacy checkout controllers. Steps: 1 zip, 2 contact (creates the
 * lead), 3 plan, 4 add-ons, 5 term + promo + review. The payment step is
 * handled by App\Livewire\Checkout\Payment (Phase 12b-3).
 */
class Funnel extends Component
{
    public int $step = 1;

    public string $zip = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $contact = '';

    public ?int $packageId = null;

    /** @var array<int, int> */
    public array $selectedAddons = [];

    public int $term = 1;

    public string $promoCode = '';

    public ?array $promo = null;

    public function mount(CheckoutFunnelService $funnel): void
    {
        $state = $funnel->state();

        $this->zip = (string) ($state['zip_code'] ?? request()->query('zip', ''));
        $this->firstName = $state['name']['first_name'] ?? '';
        $this->lastName = $state['name']['last_name'] ?? '';
        $this->email = (string) ($state['email'] ?? '');
        $this->contact = (string) ($state['contact'] ?? '');
        $this->packageId = isset($state['package_id']) ? (int) $state['package_id'] : null;
        $this->selectedAddons = array_map('intval', $state['addon_ids'] ?? []);
        $this->term = (int) ($state['term'] ?? 1);
        $this->promo = $state['promo'] ?? null;

        // Resume at the furthest step the visitor has data for.
        $this->step = match (true) {
            $this->packageId !== null => 5,
            filled($this->contact) => 3,
            filled($this->zip) => 2,
            default => 1,
        };
    }

    #[Computed]
    public function packages()
    {
        return Package::orderBy('id')->get();
    }

    #[Computed]
    public function addons()
    {
        $ids = config('checkout.addon_ids');

        return Addon::whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($a) => array_search($a->id, $ids, true))
            ->values();
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
            addonIds: $this->selectedAddons,
            discountValue: $this->promo['amount'] ?? 0.0,
            discountType: isset($this->promo['type']) ? (int) $this->promo['type'] : null,
            propertySizeId: config('checkout.property_size_id'),
            propertyTypeId: config('checkout.property_type_id'),
        );
    }

    public function submitZip(CheckoutFunnelService $funnel): void
    {
        $this->validate(['zip' => 'required|digits:5']);
        $funnel->rememberZip($this->zip);
        $this->step = 2;
    }

    public function submitContact(CheckoutFunnelService $funnel): void
    {
        $data = $this->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email',
            'contact' => 'required|string|min:10|max:20',
        ]);

        $funnel->captureContact([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'contact' => $data['contact'],
        ]);

        $this->step = 3;
    }

    public function selectPlan(int $packageId, CheckoutFunnelService $funnel): void
    {
        abort_unless(Package::whereKey($packageId)->exists(), 404);

        $this->packageId = $packageId;
        $funnel->selectPlan($packageId);
        $this->clearPromo($funnel);
        $this->step = 4;
    }

    public function toggleAddon(int $addonId, CheckoutFunnelService $funnel): void
    {
        $this->selectedAddons = in_array($addonId, $this->selectedAddons, true)
            ? array_values(array_diff($this->selectedAddons, [$addonId]))
            : [...$this->selectedAddons, $addonId];

        $funnel->setAddons($this->selectedAddons);
    }

    public function toReview(): void
    {
        $this->step = 5;
    }

    public function setTerm(int $term, CheckoutFunnelService $funnel): void
    {
        $this->term = array_key_exists($term, config('checkout.terms')) ? $term : 1;
        $funnel->put('term', $this->term);
    }

    public function applyPromo(CheckoutFunnelService $funnel, PromoCodeService $promo): void
    {
        $this->validate(['promoCode' => 'required|string|max:64']);

        $result = $this->packageId
            ? $this->firstValid($promo->autoApply($this->promoCode, $this->packageId), $promo->apply($this->promoCode))
            : $promo->apply($this->promoCode);

        if (! $result->valid) {
            $this->addError('promoCode', $result->message);
            $this->clearPromo($funnel);

            return;
        }

        $this->promo = [
            'name' => $result->promotion->name,
            'amount' => $result->discountValue(),
            'type' => $result->discountType(),
        ];
        $funnel->put('promo', $this->promo);
    }

    public function removePromo(CheckoutFunnelService $funnel): void
    {
        $this->promoCode = '';
        $this->clearPromo($funnel);
    }

    public function goToPayment(CheckoutFunnelService $funnel): mixed
    {
        if ($this->packageId === null) {
            $this->step = 3;

            return null;
        }

        $funnel->put('term', $this->term);
        $funnel->put('addon_ids', $this->selectedAddons);

        return $this->redirectRoute('checkout.payment', navigate: true);
    }

    private function firstValid(PromoResult ...$results): PromoResult
    {
        foreach ($results as $result) {
            if ($result->valid) {
                return $result;
            }
        }

        return PromoResult::invalid();
    }

    private function clearPromo(CheckoutFunnelService $funnel): void
    {
        $this->promo = null;
        $funnel->put('promo', null);
    }

    public function render(): View
    {
        return view('livewire.checkout.funnel')->layout('layouts.public', ['title' => __('Choose your plan')]);
    }
}
