<?php

namespace App\Livewire\RealEstate;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Real Estate agent home. Port of
 * RealEstate\AgentAuthController::dashboard + CompanyController::index.
 */
class Dashboard extends Component
{
    public function logout(): mixed
    {
        Auth::guard('agent')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return $this->redirectRoute('agent.login', navigate: true);
    }

    public function render(): View
    {
        $agent = Auth::guard('agent')->user();

        return view('livewire.realestate.dashboard', [
            'agent' => $agent,
            'orders' => $agent->orders()->latest('id')->limit(10)->get(),
            'orderCount' => $agent->orders()->count(),
        ])->layout('layouts.public', ['title' => __('Agent dashboard')]);
    }
}
