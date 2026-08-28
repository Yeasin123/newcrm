<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Merchant Accounts') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Authorize.Net CIM / ARB accounts — weighted selection when charging') }}</flux:text>
        </div>
        <flux:button size="sm" variant="primary" icon="plus" wire:click="newAccount">{{ __('Add account') }}</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Environment') }}</flux:table.column>
            <flux:table.column>{{ __('Weight') }}</flux:table.column>
            <flux:table.column>{{ __('Defaults') }}</flux:table.column>
            <flux:table.column>{{ __('Active') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($accounts as $acc)
                <flux:table.row :key="'aa-'.$acc->id">
                    <flux:table.cell variant="strong">{{ $acc->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$acc->environment === 'production' ? 'green' : 'amber'" inset="top bottom">{{ $acc->environment }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $acc->weight }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        @if ($acc->is_default_charge)<flux:badge size="sm" color="blue" inset="top bottom">charge</flux:badge>@endif
                        @if ($acc->is_default_client)<flux:badge size="sm" color="purple" inset="top bottom">client</flux:badge>@endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:switch :checked="$acc->is_active" wire:click="toggleActive({{ $acc->id }})" />
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $acc->id }})" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No merchant accounts configured.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showForm" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit account') : __('New account') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:select wire:model="environment" :label="__('Environment')">
                <flux:select.option value="production">production</flux:select.option>
                <flux:select.option value="sandbox">sandbox</flux:select.option>
            </flux:select>

            <flux:input wire:model="api_login_id" :label="__('API Login ID')"
                :placeholder="$editingId ? __('leave blank to keep current') : ''" />
            <flux:input wire:model="transaction_key" type="password" :label="__('Transaction Key')" viewable
                :placeholder="$editingId ? __('leave blank to keep current') : ''" />
            <flux:input wire:model="public_client_key" type="password" :label="__('Public Client Key (Accept.js)')" viewable
                :placeholder="$editingId ? __('leave blank to keep current') : ''" />
            <flux:input wire:model="signature_key" type="password" :label="__('Signature Key (webhook verify)')" viewable
                :placeholder="$editingId ? __('leave blank to keep current') : ''" />

            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="weight" type="number" :label="__('Weight')" />
                <div class="flex flex-col justify-end gap-2 pb-1">
                    <flux:checkbox wire:model="is_active" :label="__('Active')" />
                    <flux:checkbox wire:model="is_default_charge" :label="__('Default for charges')" />
                    <flux:checkbox wire:model="is_default_client" :label="__('Default for new clients')" />
                </div>
            </div>
            <flux:textarea wire:model="notes" rows="2" :label="__('Notes')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
