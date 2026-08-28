<?php

namespace App\Livewire\Portal;

use App\Models\Addon;
use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * "My plan" — the signed-in customer's package, price breakdown, add-ons,
 * term dates and full payment history. Port of Clint\EarningsController's
 * customer-facing bits, with the company-wide `PaymentHistory::all()` and
 * clobbered-variable maths removed.
 */
class PackageDetails extends Component
{
    public function render(): View
    {
        /** @var Client $client */
        $client = auth('user')->user();
        $priceInfo = json_decode((string) $client->price_cal_response) ?: null;

        $priceField = (string) $client->payment_structure === '1' ? 'monthly' : 'yearly';
        $addons = collect(json_decode((string) $client->services) ?: [])
            ->map(fn ($id) => Addon::find($id))
            ->filter()
            ->map(fn (Addon $a) => [
                'title' => $a->title,
                'price' => (float) ($a->{$priceField} ?? 0),
                'currency' => $a->currency ?? '$',
            ])
            ->values();

        return view('livewire.portal.package-details', [
            'client' => $client,
            'planTitle' => $client->propertyPackage->title ?? __('No plan on file'),
            'structure' => $priceField === 'monthly' ? __('Monthly') : __('Yearly'),
            'subtotal' => $priceInfo->price ?? null,
            'payable' => $priceInfo->discount_price ?? null,
            'discount' => $client->discount ?: null,
            'addons' => $addons,
            'startDate' => $client->start_date ? Carbon::parse($client->start_date) : null,
            'endDate' => $client->end_date ? Carbon::parse($client->end_date) : null,
            'payments' => $client->paymentHistories()->latest('payment_date')->get(),
        ])->layout('layouts.public', ['title' => __('My plan')]);
    }
}
