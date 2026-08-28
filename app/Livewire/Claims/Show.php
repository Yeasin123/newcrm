<?php

namespace App\Livewire\Claims;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Part;
use App\Models\Technician;
use App\Services\Claim\ClaimService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Show extends Component
{
    public Claim $claim;

    public string $techId = '';

    public string $techSchedule = '';

    public string $statusValue = '';

    #[Validate('required|string|min:2|max:5000')]
    public string $noteBody = '';

    // Close-claim form
    public string $closeDispatch = '';

    public string $closeSpent = '';

    public string $closeNotes = '';

    // Add-part form
    public string $partName = '';

    public string $partPrice = '';

    public string $partOrderNumber = '';

    public function mount(Claim $claim): void
    {
        $this->claim = $claim;
        $this->techId = (string) ($claim->tech_id ?? '');
        $this->techSchedule = (string) ($claim->tech_schedule ?? '');
        $this->statusValue = (string) $claim->status;
    }

    public function assignTechnician(ClaimService $service): void
    {
        $service->assignTechnician(
            $this->claim,
            $this->techId !== '' ? (int) $this->techId : null,
            $this->techSchedule ?: null,
        );
        $this->claim->refresh();
        Flux::toast(variant: 'success', text: __('Technician updated.'));
    }

    public function changeStatus(ClaimService $service): void
    {
        $status = ClaimStatus::tryFrom((int) $this->statusValue);
        abort_if($status === null, 422);

        $service->changeStatus($this->claim, $status);
        $this->claim->refresh();
        Flux::toast(variant: 'success', text: __('Status updated.'));
    }

    public function toggleCallFee(ClaimService $service): void
    {
        $service->setCallFee($this->claim, ! $this->claim->is_callfee);
        $this->claim->refresh();
    }

    public function addNote(ClaimService $service): void
    {
        $this->validateOnly('noteBody');
        $service->addNote($this->claim, $this->noteBody);
        $this->reset('noteBody');
        $this->claim->load('tracks.author');
        Flux::toast(variant: 'success', text: __('Note added.'));
    }

    public function addPart(): void
    {
        $this->validate([
            'partName' => 'required|string|max:255',
            'partPrice' => 'nullable|numeric|min:0',
            'partOrderNumber' => 'nullable|string|max:255',
        ]);

        Part::create([
            'claim_id' => $this->claim->id,
            'name' => $this->partName,
            'price' => $this->partPrice !== '' ? (float) $this->partPrice : null,
            'order_number' => $this->partOrderNumber ?: null,
            'who_buy' => 1,
        ]);

        $this->reset('partName', 'partPrice', 'partOrderNumber');
        $this->claim->load('parts');
        Flux::toast(variant: 'success', text: __('Part added.'));
    }

    public function closeClaim(ClaimService $service): void
    {
        $this->validate([
            'closeDispatch' => 'nullable|numeric|min:0',
            'closeSpent' => 'nullable|numeric|min:0',
            'closeNotes' => 'nullable|string|max:5000',
        ]);

        $service->close($this->claim, [
            'dispatch_amount' => $this->closeDispatch ?: 0,
            'claim_spent_price' => $this->closeSpent !== '' ? (float) $this->closeSpent : null,
            'notes' => $this->closeNotes ?: null,
        ]);

        $this->reset('closeDispatch', 'closeSpent', 'closeNotes');
        $this->claim->refresh();
        $this->statusValue = (string) $this->claim->status;
        Flux::toast(variant: 'success', text: __('Claim closed.'));
    }

    public function render(): View
    {
        $this->claim->loadMissing([
            'client:id,first_name,last_name,email,contact,policy_number',
            'technician:id,first_name,last_name,business_name,contact,email',
            'systemRef:id,title', 'brandRef:id,title',
            'parts', 'files', 'tracks.author:id,first_name,last_name', 'closedDetails',
        ]);

        return view('livewire.claims.show', [
            'statuses' => ClaimStatus::cases(),
            'technicians' => Technician::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'business_name']),
        ])->layout('layouts.app', ['title' => 'Claim '.($this->claim->claim_no ?: $this->claim->id)]);
    }
}
