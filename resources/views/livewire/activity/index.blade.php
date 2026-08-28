<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Activity') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Salesman actions on leads') }}</flux:text>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <flux:select wire:model.live="salesman" :placeholder="__('All salesmen')">
            <flux:select.option value="">{{ __('All salesmen') }}</flux:select.option>
            @foreach ($salesmen as $s)
                <flux:select.option value="{{ $s->id }}" wire:key="sm-{{ $s->id }}">{{ $s->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="action" :placeholder="__('Any action')">
            <flux:select.option value="">{{ __('Any action') }}</flux:select.option>
            @foreach ($actions as $a)
                <flux:select.option value="{{ $a }}" wire:key="ac-{{ $a }}">{{ ucwords(str_replace('_', ' ', $a)) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input type="date" wire:model.live="date" />
    </div>

    <flux:table :paginate="$rows">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Salesman') }}</flux:table.column>
            <flux:table.column>{{ __('Lead') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
            <flux:table.column>{{ __('Lead status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($rows as $row)
                <flux:table.row :key="'ta-'.$row->id">
                    <flux:table.cell class="text-zinc-500">{{ optional($row->STORE_DATE)->format('M j, Y g:ia') ?? '—' }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $row->salesman?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <a class="text-blue-600 hover:underline dark:text-blue-400" wire:navigate href="{{ route('leads.show', $row->lead_id) }}">#{{ $row->lead_id }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($actions as $a)
                                @if (filled($row->{$a}))
                                    <flux:badge size="sm" color="zinc" inset="top bottom">{{ str_replace('_', ' ', $a) }}</flux:badge>
                                @endif
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $row->lead_status ?: '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No activity matches these filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
