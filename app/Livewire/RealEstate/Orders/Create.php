<?php

namespace App\Livewire\RealEstate\Orders;

use App\Models\Addon;
use App\Models\Package;
use App\Models\PropertySize;
use App\Models\PropertyType;
use App\Services\RealEstate\AgentOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Real Estate order wizard. Port of RealEstate\OrderController's
 * property → plan → buyer → options → closing → final steps. The Dompdf
 * invoice + invoice email stay deferred (Phase 13).
 */
class Create extends Component
{
    public int $step = 1;

    // Step 1 — property
    public string $address_one = '';

    public string $address_two = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    public ?int $property_size = null;

    public ?int $property_name = null;

    // Step 2 — plan
    public ?int $planId = null;

    // Step 3 — buyer
    public string $buyer_name = '';

    public string $buyer_email = '';

    public string $buyer_phone = '';

    // Step 4 — add-ons
    /** @var array<int, int> */
    public array $selectedAddons = [];

    // Step 5 — closing / agents (all optional)
    public string $closing_name = '';

    public string $closing_email = '';

    public string $buyer_agent_name = '';

    public string $buyer_agent_email = '';

    public string $seller_agent_name = '';

    public string $seller_agent_email = '';

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

    #[Computed]
    public function planPrice(): float
    {
        $plan = $this->planId ? Package::find($this->planId) : null;

        return $plan ? (float) ($plan->yearly ?: $plan->monthly) : 0.0;
    }

    #[Computed]
    public function total(): float
    {
        $addons = Addon::whereIn('id', $this->selectedAddons)->get();
        $addonTotal = $addons->sum(fn (Addon $a) => (float) ($a->yearly ?: $a->monthly));

        return round($this->planPrice + $addonTotal, 2);
    }

    public function stepProperty(): void
    {
        $this->validate([
            'address_one' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postal_code' => 'required|string',
            'property_size' => 'required|integer',
            'property_name' => 'required|integer',
        ]);
        $this->step = 2;
    }

    public function stepPlan(): void
    {
        $this->validate(['planId' => 'required|integer']);
        $this->step = 3;
    }

    public function stepBuyer(): void
    {
        $this->validate([
            'buyer_name' => 'required|string',
            'buyer_email' => 'required|email',
            'buyer_phone' => 'required|string',
        ]);
        $this->step = 4;
    }

    public function stepOptions(): void
    {
        $this->step = 5;
    }

    public function stepClosing(): void
    {
        $this->validate([
            'closing_email' => 'nullable|email',
            'buyer_agent_email' => 'nullable|email',
            'seller_agent_email' => 'nullable|email',
        ]);
        $this->step = 6;
    }

    public function placeOrder(AgentOrderService $orders): mixed
    {
        $plan = Package::findOrFail($this->planId);
        $addons = Addon::whereIn('id', $this->selectedAddons)->get()
            ->map(fn (Addon $a) => ['id' => $a->id, 'name' => $a->title, 'price' => (float) ($a->yearly ?: $a->monthly)])
            ->values()->all();

        $result = $orders->place(Auth::guard('agent')->user(), [
            'property' => [
                'address_one' => $this->address_one,
                'address_two' => $this->address_two,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'property_size' => $this->property_size,
                'property_name' => $this->property_name,
            ],
            'plan' => ['name' => $plan->title, 'price' => $this->planPrice],
            'buyer' => ['name' => $this->buyer_name, 'email' => $this->buyer_email, 'phone' => $this->buyer_phone],
            'addons' => $addons,
            'closing' => $this->optionalParty($this->closing_name, $this->closing_email),
            'buyer_agent' => $this->optionalParty($this->buyer_agent_name, $this->buyer_agent_email),
            'seller_agent' => $this->optionalParty($this->seller_agent_name, $this->seller_agent_email),
        ]);

        return $this->redirectRoute('agent.order.show', ['order' => $result['order']->id], navigate: true);
    }

    private function optionalParty(string $name, string $email): ?array
    {
        return ($name !== '' || $email !== '') ? ['name' => $name, 'email' => $email] : null;
    }

    public function render(): View
    {
        return view('livewire.realestate.orders.create')->layout('layouts.public', ['title' => __('New order')]);
    }
}
