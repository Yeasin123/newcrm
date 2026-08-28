<?php

namespace App\Livewire\Portal;

use App\Models\Client;
use App\Services\Portal\PortalLoginService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Customer-portal home — a summary of the signed-in customer's OWN policy.
 * (The legacy Clint\DashboardController showed company-wide counts, copied
 * unchanged from the admin dashboard; that is dropped.)
 */
class Dashboard extends Component
{
    public function logout(PortalLoginService $auth): mixed
    {
        $auth->logout();

        return $this->redirectRoute('login.user', navigate: true);
    }

    public function render(): View
    {
        /** @var Client $client */
        $client = auth('user')->user();

        $priceInfo = json_decode((string) $client->price_cal_response) ?: null;

        return view('livewire.portal.dashboard', [
            'client' => $client,
            'planTitle' => $client->propertyPackage->title ?? __('No plan on file'),
            'structure' => (string) $client->payment_structure === '2' ? __('Yearly') : __('Monthly'),
            'amount' => $priceInfo->discount_price ?? $priceInfo->price ?? null,
            'nextBill' => $client->start_date ? Carbon::parse($client->start_date)->isFuture()
                ? Carbon::parse($client->start_date)
                : null
                : null,
            'recentPayments' => $client->paymentHistories()->latest('payment_date')->limit(5)->get(),
            'openClaims' => $client->claims()->whereIn('status', [0, 1])->count(),
        ])->layout('layouts.public', ['title' => __('My account')]);
    }
}
