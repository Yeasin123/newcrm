<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Payment Health') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Monthly billing roll-up') }}</flux:text>
        </div>
        <div class="flex items-end gap-2">
            <flux:input type="month" wire:model.live="month" :label="__('Month')" />
            <flux:button size="sm" variant="ghost" icon="bookmark" wire:click="saveSnapshot">{{ __('Save snapshot') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            ['Paid', $metrics['paid_count'], '$'.number_format($metrics['gross_amount'], 2), 'green'],
            ['Failed', $metrics['failed_count'], '$'.number_format($metrics['failed_amount'], 2), 'red'],
            ['Refunds', $metrics['refund_count'], '$'.number_format($metrics['refund_amount'], 2), 'amber'],
            ['Disputes', $metrics['dispute_count'], '$'.number_format($metrics['dispute_amount'], 2), 'purple'],
            ['Renewals queued', $metrics['renewal_count'], '', 'zinc'],
        ] as [$label, $count, $amount, $color])
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="m-{{ $label }}">
                <flux:text class="text-sm text-zinc-500">{{ __($label) }}</flux:text>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($count) }}</div>
                @if ($amount)<div class="text-sm text-zinc-500">{{ $amount }}</div>@endif
            </div>
        @endforeach
    </div>

    <flux:card>
        <div class="flex items-baseline justify-between">
            <flux:heading size="lg">{{ __('Net this month') }}</flux:heading>
            <div class="text-2xl font-semibold">${{ number_format($metrics['net_amount'], 2) }}</div>
        </div>
        <flux:text class="text-xs text-zinc-500">{{ __('gross − refunds − lost disputes') }}</flux:text>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-3">{{ __('Open events') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($openEvents as $e)
                    <flux:table.row :key="'e-'.$e->id">
                        <flux:table.cell class="text-zinc-500">{{ optional($e->created_at)->format('M j, Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $e->event_type }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $e->client ? trim($e->client->first_name.' '.$e->client->last_name) : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $e->title }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="xs" variant="ghost" wire:click="resolveEvent({{ $e->id }})">{{ __('Resolve') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No open events.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
