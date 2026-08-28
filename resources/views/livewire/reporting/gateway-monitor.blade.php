<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('Gateway Monitor') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Authorize.Net webhook stream + log errors/retries.') }}</flux:text>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            [__('Events'), $summary['total_events']],
            [__('With payload id'), $summary['with_payload_id']],
            [__('Log errors'), $summary['error_count']],
            [__('Log retries'), $summary['retry_count']],
        ] as [$label, $value])
            <flux:card class="p-4">
                <flux:text class="text-xs text-zinc-500">{{ $label }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($value) }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="authorize_account_id" class="max-w-[13rem]">
            <flux:select.option :value="null">{{ __('Any merchant account') }}</flux:select.option>
            @foreach ($accounts as $a)
                <flux:select.option :value="$a->id">{{ $a->name }} ({{ $a->environment }})</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="event_type" :placeholder="__('Event type')" class="max-w-[14rem]" clearable />
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('event id / payload id / raw')" class="max-w-sm" clearable />
        <flux:input type="date" wire:model.live="date_from" class="max-w-[10rem]" />
        <flux:input type="date" wire:model.live="date_to" class="max-w-[10rem]" />
    </div>

    <div class="flex flex-wrap gap-2 text-xs text-zinc-500">
        @foreach ($eventStats as $stat)
            <span class="rounded-md bg-zinc-100 px-2 py-1 dark:bg-white/10">{{ $stat->event_type ?: '—' }}: {{ number_format($stat->total) }}</span>
        @endforeach
    </div>

    <flux:table :paginate="$events">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Account') }}</flux:table.column>
            <flux:table.column>{{ __('Event type') }}</flux:table.column>
            <flux:table.column>{{ __('Payload id') }}</flux:table.column>
            <flux:table.column>{{ __('Event id') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($events as $e)
                <flux:table.row :key="'e-'.$e->id">
                    <flux:table.cell class="text-zinc-500">{{ optional($e->created_at)->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $e->authorizeAccount?->name ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $e->event_type ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $e->payload_id ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs text-zinc-500">{{ \Illuminate\Support\Str::limit($e->event_id, 24) }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No webhook events.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Recent ANet log errors') }}</flux:heading>
            <div class="mt-2 flex max-h-64 flex-col divide-y divide-zinc-200 overflow-y-auto text-xs dark:divide-zinc-700">
                @forelse ($errorLogs as $l)
                    <div class="py-2"><span class="text-zinc-400">{{ $l['time'] }}</span> · {{ \Illuminate\Support\Str::limit($l['message'], 160) }}</div>
                @empty
                    <div class="py-2 text-zinc-500">{{ __('None.') }}</div>
                @endforelse
            </div>
        </flux:card>
        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Recent ANet retries') }}</flux:heading>
            <div class="mt-2 flex max-h-64 flex-col divide-y divide-zinc-200 overflow-y-auto text-xs dark:divide-zinc-700">
                @forelse ($retryLogs as $l)
                    <div class="py-2"><span class="text-zinc-400">{{ $l['time'] }}</span> · {{ \Illuminate\Support\Str::limit($l['message'], 160) }}</div>
                @empty
                    <div class="py-2 text-zinc-500">{{ __('None.') }}</div>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
