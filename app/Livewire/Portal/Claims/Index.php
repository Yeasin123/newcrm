<?php

namespace App\Livewire\Portal\Claims;

use App\Models\Claim;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The signed-in customer's claims. Port of Clint\ClimController::claim,
 * scoped to the customer's own rows.
 */
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.portal.claims.index', [
            'claims' => Claim::with(['systemRef', 'brandRef'])
                ->where('client_id', auth('user')->id())
                ->latest('id')
                ->get(),
        ])->layout('layouts.public', ['title' => __('Claims')]);
    }
}
