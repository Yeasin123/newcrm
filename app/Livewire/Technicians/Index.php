<?php

namespace App\Livewire\Technicians;

use App\Models\Technician;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $scope = 'all'; // all | internal | external

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'scope'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $rows = Technician::query()
            ->withCount('claims')
            ->search($this->search)
            ->when($this->scope === 'internal', fn ($q) => $q->where('in_ex', 0))
            ->when($this->scope === 'external', fn ($q) => $q->where('in_ex', 1))
            ->orderBy('first_name')
            ->paginate(30);

        return view('livewire.technicians.index', [
            'technicians' => $rows,
        ])->layout('layouts.app', ['title' => 'Technicians']);
    }
}
