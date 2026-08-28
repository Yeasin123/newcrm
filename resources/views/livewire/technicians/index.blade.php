<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Technicians') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ number_format($technicians->total()) }} {{ __('technicians') }}</flux:text>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Name, business, email, phone')" class="max-w-sm" clearable />
        <div class="flex gap-2">
            @foreach (['all' => __('All'), 'internal' => __('Internal'), 'external' => __('External')] as $key => $label)
                <flux:button size="sm" :variant="$scope === $key ? 'primary' : 'ghost'"
                    wire:click="$set('scope', '{{ $key }}')" wire:key="sc-{{ $key }}">{{ $label }}</flux:button>
            @endforeach
        </div>
    </div>

    <flux:table :paginate="$technicians">
        <flux:table.columns>
            <flux:table.column>{{ __('Technician') }}</flux:table.column>
            <flux:table.column>{{ __('Contact') }}</flux:table.column>
            <flux:table.column>{{ __('Call fee') }}</flux:table.column>
            <flux:table.column>{{ __('Claims') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($technicians as $tech)
                <flux:table.row :key="'t-'.$tech->id" class="cursor-pointer" wire:navigate href="{{ route('technicians.show', $tech) }}">
                    <flux:table.cell variant="strong">
                        <div>{{ $tech->full_name }}</div>
                        <div class="text-xs text-zinc-500">{{ $tech->business_name }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        <div>{{ $tech->contact ?: '—' }}</div>
                        <div class="text-xs">{{ $tech->email }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $tech->call_fee ? '$'.number_format((float) $tech->call_fee, 2) : '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $tech->claims_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ $tech->in_ex ? __('External') : __('Internal') }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No technicians found.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
