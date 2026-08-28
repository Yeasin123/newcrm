<?php

namespace App\Livewire\Claims;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Technician;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $technician = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'technician'], true)) {
            $this->resetPage();
        }
    }

    protected function query(): Builder
    {
        return Claim::query()
            ->with(['client:id,first_name,last_name,policy_number', 'technician:id,first_name,last_name,business_name', 'systemRef:id,title'])
            ->search($this->search)
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->technician !== '', fn (Builder $q) => $q->where('tech_id', $this->technician));
    }

    public function exportParams(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
            'technician' => $this->technician,
        ], fn ($v) => $v !== '' && $v !== null);
    }

    public function render(): View
    {
        return view('livewire.claims.index', [
            'claims' => $this->query()->orderByDesc('id')->paginate(30),
            'statuses' => ClaimStatus::cases(),
            'technicians' => Technician::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'business_name']),
        ])->layout('layouts.app', ['title' => 'Claims']);
    }
}
