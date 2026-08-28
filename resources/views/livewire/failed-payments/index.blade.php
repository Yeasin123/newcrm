<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Failed Payments') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ __(':total cases', ['total' => number_format($metrics['total'])]) }}
                &middot; {{ __(':n open', ['n' => $metrics['open']]) }}
                &middot; {{ __(':r% recovered', ['r' => $metrics['recovery_rate']]) }}
            </flux:text>
        </div>
        <div class="flex items-center gap-2">
            @if ($canRetry)
                <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="runRetries"
                    wire:loading.attr="disabled">{{ __('Run retries') }}</flux:button>
            @endif
            <flux:button size="sm" variant="ghost" :href="route('failed-payments.settings')" wire:navigate icon="cog-6-tooth">
                {{ __('Settings') }}
            </flux:button>
        </div>
    </div>

    @if ($retryOutput !== '')
        <flux:callout variant="secondary" icon="command-line">
            <pre class="whitespace-pre-wrap text-xs">{{ $retryOutput }}</pre>
        </flux:callout>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach ([
            'total' => __('Total'), 'new' => __('New'), 'open' => __('Open'),
            'under_review' => __('Review'), 'unmatched' => __('Unmatched'),
            'recovered' => __('Recovered'), 'resolved' => __('Resolved'),
        ] as $key => $label)
            <flux:card class="p-4">
                <flux:text class="text-xs text-zinc-500">{{ $label }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($metrics[$key] ?? 0) }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            :placeholder="__('Case, subscription, transaction, customer…')" class="max-w-sm" clearable />

        <flux:select wire:model.live="status" class="max-w-[10rem]">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option :value="$s">{{ ucfirst(str_replace('_', ' ', $s)) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="frequency" class="max-w-[9rem]">
            <flux:select.option value="">{{ __('Any freq.') }}</flux:select.option>
            @foreach (['monthly', 'yearly', 'bank', 'card'] as $f)
                <flux:select.option :value="$f">{{ ucfirst($f) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="retry_status" class="max-w-[10rem]">
            <flux:select.option value="">{{ __('Any retry') }}</flux:select.option>
            @foreach (['pending', 'paused', 'processing', 'completed', 'cancelled', 'failed', 'exhausted', 'simulated'] as $r)
                <flux:select.option :value="$r">{{ ucfirst($r) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="assignment" class="max-w-[10rem]">
            <flux:select.option value="all">{{ __('All assignments') }}</flux:select.option>
            <flux:select.option value="assigned">{{ __('Assigned') }}</flux:select.option>
            <flux:select.option value="unassigned">{{ __('Unassigned') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="assigned_user_id" class="max-w-[12rem]">
            <flux:select.option :value="null">{{ __('Any admin') }}</flux:select.option>
            @foreach ($assignmentUsers as $u)
                <flux:select.option :value="$u->id">{{ trim($u->first_name.' '.$u->last_name) ?: $u->email }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input type="date" wire:model.live="from_date" class="max-w-[10rem]" :label="__('From')" />
        <flux:input type="date" wire:model.live="to_date" class="max-w-[10rem]" :label="__('To')" />

        <flux:button size="sm" variant="ghost" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
    </div>

    <flux:table :paginate="$cases">
        <flux:table.columns>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Freq.') }}</flux:table.column>
            <flux:table.column>{{ __('Assigned') }}</flux:table.column>
            <flux:table.column>{{ __('Latest failed') }}</flux:table.column>
            <flux:table.column>{{ __('Retries') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($cases as $c)
                <flux:table.row :key="'fp-'.$c->id">
                    <flux:table.cell variant="strong">
                        <a class="hover:underline" wire:navigate href="{{ route('failed-payments.show', $c->case_uuid) }}">
                            {{ trim(($c->client?->first_name ?? '').' '.($c->client?->last_name ?? '')) ?: ($c->client?->email ?? __('Client #').$c->client_id) }}
                        </a>
                        <div class="text-xs text-zinc-500">#{{ $c->client?->policy_number ?: '—' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>${{ number_format((float) $c->failed_amount, 2) }} {{ $c->currency && $c->currency !== '$' ? $c->currency : '' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($c->current_status) {
                            'recovered', 'resolved' => 'green',
                            'cancelled' => 'zinc',
                            'unmatched' => 'amber',
                            default => 'red',
                        }">{{ ucfirst(str_replace('_', ' ', $c->current_status)) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $c->failure_category ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $c->payment_frequency ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        {{ $c->assignedUser ? (trim($c->assignedUser->first_name.' '.$c->assignedUser->last_name) ?: $c->assignedUser->email) : '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ optional($c->latest_failed_at)->format('Y-m-d H:i') ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $c->retry_schedules_count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center text-zinc-500">{{ __('No failed-payment cases match these filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
