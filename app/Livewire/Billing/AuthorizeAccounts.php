<?php

namespace App\Livewire\Billing;

use App\Models\AuthorizeAccount;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AuthorizeAccounts extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    // form fields
    public string $name = '';

    public string $environment = 'production';

    public string $api_login_id = '';

    public string $transaction_key = '';

    public string $public_client_key = '';

    public string $signature_key = '';

    public int $weight = 100;

    public bool $is_active = true;

    public bool $is_default_charge = false;

    public bool $is_default_client = false;

    public string $notes = '';

    public function newAccount(): void
    {
        $this->reset('editingId', 'name', 'api_login_id', 'transaction_key', 'public_client_key', 'signature_key', 'notes');
        $this->environment = 'production';
        $this->weight = 100;
        $this->is_active = true;
        $this->is_default_charge = false;
        $this->is_default_client = false;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $acc = AuthorizeAccount::findOrFail($id);
        $this->editingId = $acc->id;
        $this->name = $acc->name;
        $this->environment = $acc->environment;
        // Credentials are never pre-filled; blank means "keep existing".
        $this->api_login_id = '';
        $this->transaction_key = '';
        $this->public_client_key = '';
        $this->signature_key = '';
        $this->weight = (int) $acc->weight;
        $this->is_active = (bool) $acc->is_active;
        $this->is_default_charge = (bool) $acc->is_default_charge;
        $this->is_default_client = (bool) $acc->is_default_client;
        $this->notes = (string) $acc->notes;
        $this->showForm = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => 'required|string|max:255',
            'environment' => 'required|in:sandbox,production',
            'weight' => 'required|integer|min:1|max:1000',
            'notes' => 'nullable|string|max:2000',
        ];

        if ($this->editingId === null) {
            $rules['api_login_id'] = 'required|string|max:255';
            $rules['transaction_key'] = 'required|string|max:255';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'environment' => $this->environment,
            'weight' => $this->weight,
            'is_active' => $this->is_active,
            'is_default_charge' => $this->is_default_charge,
            'is_default_client' => $this->is_default_client,
            'notes' => $this->notes ?: null,
        ];

        foreach (['api_login_id', 'transaction_key', 'public_client_key', 'signature_key'] as $field) {
            if ($this->{$field} !== '') {
                $data[$field] = $this->{$field};
            }
        }

        $acc = $this->editingId
            ? tap(AuthorizeAccount::findOrFail($this->editingId))->update($data)
            : AuthorizeAccount::create($data);

        $this->enforceSingleDefaults($acc);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: __('Merchant account saved.'));
    }

    public function toggleActive(int $id): void
    {
        $acc = AuthorizeAccount::findOrFail($id);
        $acc->update(['is_active' => ! $acc->is_active]);
    }

    /** Only one account may be default-charge / default-client at a time. */
    private function enforceSingleDefaults(AuthorizeAccount $acc): void
    {
        if ($acc->is_default_charge) {
            AuthorizeAccount::whereKeyNot($acc->id)->update(['is_default_charge' => false]);
        }
        if ($acc->is_default_client) {
            AuthorizeAccount::whereKeyNot($acc->id)->update(['is_default_client' => false]);
        }
    }

    public function render(): View
    {
        return view('livewire.billing.authorize-accounts', [
            'accounts' => AuthorizeAccount::query()->orderByDesc('is_active')->orderByDesc('weight')->get(),
        ])->layout('layouts.app', ['title' => 'Merchant Accounts']);
    }
}
