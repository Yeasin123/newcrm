<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Claims') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ number_format($claims->total()) }} {{ __('claims') }}</flux:text>
        </div>
        <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
            href="{{ route('claims.export', $this->exportParams()) }}">{{ __('Export CSV') }}</flux:button>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Claim #, serial, customer')" clearable />
        <flux:select wire:model.live="status" :placeholder="__('Any status')">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option value="{{ $s->value }}" wire:key="st-{{ $s->value }}">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="technician" :placeholder="__('Any technician')">
            <flux:select.option value="">{{ __('Any technician') }}</flux:select.option>
            @foreach ($technicians as $t)
                <flux:select.option value="{{ $t->id }}" wire:key="tc-{{ $t->id }}">{{ $t->full_name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$claims">
        <flux:table.columns>
            <flux:table.column>{{ __('Claim #') }}</flux:table.column>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('System') }}</flux:table.column>
            <flux:table.column>{{ __('Technician') }}</flux:table.column>
            <flux:table.column>{{ __('Total') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Opened') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($claims as $claim)
                <flux:table.row :key="'cl-'.$claim->id" class="cursor-pointer" wire:navigate href="{{ route('claims.show', $claim) }}">
                    <flux:table.cell variant="strong">{{ $claim->claim_no ?: $claim->id }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">{{ $claim->client ? trim($claim->client->first_name.' '.$claim->client->last_name) : '—' }}</div>
                        <div class="text-xs text-zinc-500">#{{ $claim->client->policy_number ?? '—' }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $claim->systemRef->title ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $claim->technician?->full_name ?? __('Unassigned') }}</flux:table.cell>
                    <flux:table.cell variant="strong">${{ number_format((float) $claim->total_price, 2) }}</flux:table.cell>
                    <flux:table.cell>
                        @php($cs = \App\Enums\ClaimStatus::tryFrom((int) $claim->status))
                        <flux:badge size="sm" :color="$cs?->color() ?? 'zinc'" inset="top bottom">{{ $cs?->label() ?? $claim->status }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ optional($claim->created_at)->format('M j, Y') ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="7" class="text-center text-zinc-500">{{ __('No claims match these filters.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
