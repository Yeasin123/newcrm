<?php

namespace App\Livewire\SalesmanMessages;

use App\Models\User;
use App\Services\Messaging\SalesmanMessageService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin↔salesman SMS inbox. Ported from the legacy SalesmanMessageController
 * index/store views. Superadmin-only, matching the legacy authorizeAdmin
 * (role_id IS NULL).
 */
class Inbox extends Component
{
    #[Url]
    public ?int $salesmanId = null;

    public string $body = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    protected function service(): SalesmanMessageService
    {
        return app(SalesmanMessageService::class);
    }

    public function selectSalesman(int $id): void
    {
        $this->salesmanId = $id;
        $this->service()->markThreadRead((int) auth()->id(), $id);
    }

    public function send(): void
    {
        $this->validate([
            'salesmanId' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $salesman = User::query()->whereNotNull('role_id')->findOrFail($this->salesmanId);
        $message = $this->service()->send((int) auth()->id(), $salesman, $this->body);

        $this->body = '';

        $message->status === 'sent'
            ? Flux::toast(variant: 'success', text: __('Message sent.'))
            : Flux::toast(variant: 'warning', text: __('Saved, but not delivered: :err', ['err' => $message->error_message]));
    }

    public function render(): View
    {
        $adminId = (int) auth()->id();
        $salesmen = $this->service()->orderedSalesmen($adminId);

        if ($this->salesmanId === null && $salesmen->isNotEmpty()) {
            $this->salesmanId = $salesmen->first()->id;
            $this->service()->markThreadRead($adminId, $this->salesmanId);
        }

        $selected = $salesmen->firstWhere('id', $this->salesmanId);

        return view('livewire.salesman-messages.inbox', [
            'salesmen' => $salesmen,
            'selected' => $selected,
            'messages' => $selected ? $this->service()->thread($adminId, $selected->id) : collect(),
            'unreadCounts' => $this->service()->unreadCounts($adminId),
        ])->layout('layouts.app', ['title' => 'Salesman Messages']);
    }
}
