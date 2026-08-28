<?php

namespace App\Livewire\RealEstate\Orders;

use App\Models\RealEstate\AgentOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** A placed Real Estate order, readable only by the agent who created it. */
class Show extends Component
{
    #[Locked]
    public int $orderId;

    public function mount(int $order): void
    {
        $this->orderId = $order;

        abort_unless(
            AgentOrder::where('id', $order)->where('agent_id', Auth::guard('agent')->id())->exists(),
            404,
        );
    }

    public function render(): View
    {
        return view('livewire.realestate.orders.show', [
            'order' => AgentOrder::findOrFail($this->orderId),
        ])->layout('layouts.public', ['title' => __('Order details')]);
    }
}
