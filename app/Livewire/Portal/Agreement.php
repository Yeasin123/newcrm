<?php

namespace App\Livewire\Portal;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * "Your agreement" — signature status and the most recent renewal agreement
 * on file. Port of Clint\AgreementController::agreementView. Rendering /
 * re-signing the full policy PDF stays deferred (Phase 7 + Phase 13).
 */
class Agreement extends Component
{
    public function render(): View
    {
        /** @var Client $client */
        $client = auth('user')->user();

        return view('livewire.portal.agreement', [
            'client' => $client,
            'signed' => filled($client->signature_date_time),
            'latestRenewalPdf' => $client->renewAgreementPdfs()->first(),
        ])->layout('layouts.public', ['title' => __('Agreement')]);
    }
}
