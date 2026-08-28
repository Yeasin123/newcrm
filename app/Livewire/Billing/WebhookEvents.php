<?php

namespace App\Livewire\Billing;

use App\Models\AnetWebhookEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WebhookEvents extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $rows = AnetWebhookEvent::query()
            ->with('account:id,name')
            ->when($this->search !== '', fn (Builder $q) => $q->where('event_id', 'like', "%{$this->search}%")->orWhere('payload_id', 'like', "%{$this->search}%"))
            ->when($this->type !== '', fn (Builder $q) => $q->where('event_type', $this->type))
            ->orderByDesc('id')
            ->paginate(40);

        return view('livewire.billing.webhook-events', [
            'rows' => $rows,
            'types' => AnetWebhookEvent::query()->whereNotNull('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
        ])->layout('layouts.app', ['title' => 'Webhook Events']);
    }
}
