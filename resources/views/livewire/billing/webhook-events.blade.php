<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Webhook Events') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Inbound Authorize.Net notify events (idempotency store)') }}</flux:text>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Event ID or payload ID')" clearable />
        <flux:select wire:model.live="type" :placeholder="__('Any type')">
            <flux:select.option value="">{{ __('Any type') }}</flux:select.option>
            @foreach ($types as $t)
                <flux:select.option value="{{ $t }}" wire:key="ty-{{ $loop->index }}">{{ $t }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$rows">
        <flux:table.columns>
            <flux:table.column>{{ __('Received') }}</flux:table.column>
            <flux:table.column>{{ __('Account') }}</flux:table.column>
            <flux:table.column>{{ __('Event type') }}</flux:table.column>
            <flux:table.column>{{ __('Event ID') }}</flux:table.column>
            <flux:table.column>{{ __('Payload ID') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($rows as $row)
                <flux:table.row :key="'we-'.$row->id">
                    <flux:table.cell class="text-zinc-500">{{ optional($row->created_at)->format('M j, Y g:ia') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $row->account->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $row->event_type ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($row->event_id, 20) }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs text-zinc-500">{{ $row->payload_id ?: '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No webhook events recorded yet.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
