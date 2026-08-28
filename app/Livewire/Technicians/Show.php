<?php

namespace App\Livewire\Technicians;

use App\Models\Technician;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Technician $technician;

    public function mount(Technician $technician): void
    {
        $this->technician = $technician;
    }

    public function render(): View
    {
        $claims = $this->technician->claims()
            ->with('client:id,first_name,last_name,policy_number')
            ->latest('id')
            ->limit(50)
            ->get();

        $ratings = $this->technician->ratings()->latest('id')->limit(20)->get();

        return view('livewire.technicians.show', [
            'claims' => $claims,
            'ratings' => $ratings,
            'avgStars' => round((float) $this->technician->ratings()->avg('stars'), 1),
            'openCount' => $this->technician->claims()->where('status', 0)->count(),
        ])->layout('layouts.app', ['title' => $this->technician->full_name]);
    }
}
