<?php

namespace App\Livewire\Portal\Claims;

use App\Models\Claim;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A single claim, readable only by the customer who owns it. Port of
 * Clint\ClimController::claimDetail.
 */
class Show extends Component
{
    #[Locked]
    public int $claimId;

    public function mount(int $claim): void
    {
        $this->claimId = $claim;

        abort_unless(
            Claim::where('id', $claim)->where('client_id', auth('user')->id())->exists(),
            404,
        );
    }

    public function render(): View
    {
        return view('livewire.portal.claims.show', [
            'claim' => Claim::with(['systemRef', 'brandRef', 'issueRef', 'tracks', 'closedDetails'])
                ->findOrFail($this->claimId),
        ])->layout('layouts.public', ['title' => __('Claim details')]);
    }
}
