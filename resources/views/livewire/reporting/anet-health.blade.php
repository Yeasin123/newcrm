@php $money = fn ($v) => '$'.number_format((float) $v, 2); @endphp

<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div>
        <flux:heading size="xl">{{ __('ANet Recurring Health') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            {{ __('Local subscription records vs Authorize.Net.') }}
            @unless ($liveChecksOn)
                <span class="text-amber-600">· {{ __('Live gateway checks are OFF (config/reporting.php).') }}</span>
            @endunless
        </flux:text>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ([
            [__('Accounts'), $totals['accounts']],
            [__('Active'), $totals['active_accounts']],
            [__('Local subscriptions'), $totals['local_subscriptions']],
            [__('Failed payments'), $totals['failed_payments']],
            [__('Webhook events'), $totals['webhook_events']],
        ] as [$label, $value])
            <flux:card class="p-4">
                <flux:text class="text-xs text-zinc-500">{{ $label }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ number_format($value) }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Per merchant account') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Account') }}</flux:table.column>
                <flux:table.column>{{ __('Env') }}</flux:table.column>
                <flux:table.column>{{ __('Subscriptions') }}</flux:table.column>
                <flux:table.column>{{ __('Active clients') }}</flux:table.column>
                <flux:table.column>{{ __('Failed payments') }}</flux:table.column>
                <flux:table.column>{{ __('Last webhook') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($summaries as $s)
                    <flux:table.row :key="'acc-'.$s['account']->id">
                        <flux:table.cell variant="strong">{{ $s['account']->name }}
                            @unless ($s['account']->is_active)<flux:badge size="sm" color="zinc" class="ml-1">{{ __('inactive') }}</flux:badge>@endunless
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $s['account']->environment }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($s['local_subscription_count']) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($s['local_active_clients']) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($s['local_failed_payments']) }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $s['last_webhook_at'] ? \Illuminate\Support\Carbon::parse($s['last_webhook_at'])->format('Y-m-d H:i') : '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="authorize_account_id" class="max-w-[13rem]">
            <flux:select.option :value="null">{{ __('Any merchant account') }}</flux:select.option>
            @foreach ($accounts as $a)
                <flux:select.option :value="$a->id">{{ $a->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="local_status" class="max-w-[10rem]">
            <flux:select.option value="failed">{{ __('Local failed') }}</flux:select.option>
            <flux:select.option value="paid">{{ __('Local paid') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('customer / subscription / transaction')" class="max-w-sm" clearable />
    </div>

    <flux:table :paginate="$rows">
        <flux:table.columns>
            <flux:table.column>{{ __('Customer') }}</flux:table.column>
            <flux:table.column>{{ __('Subscription') }}</flux:table.column>
            <flux:table.column>{{ __('Local') }}</flux:table.column>
            <flux:table.column>{{ __('Gateway') }}</flux:table.column>
            <flux:table.column>{{ __('Verdict') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($rows as $p)
                <flux:table.row :key="'r-'.$p->id">
                    <flux:table.cell variant="strong">
                        @if ($p->userInfo)
                            <a class="hover:underline" wire:navigate href="{{ route('customers.show', $p->userInfo) }}">
                                {{ trim($p->userInfo->first_name.' '.$p->userInfo->last_name) ?: $p->userInfo->email }}
                            </a>
                        @else
                            {{ __('Client #').$p->client_id }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $p->subscription_id }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="(int) $p->payment_status === 1 ? 'green' : 'red'">
                            {{ (int) $p->payment_status === 1 ? __('paid') : __('failed') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $p->live_check['gateway_status'] ?? ($p->live_check['status'] ?? '—') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match ($p->match_status['level']) { 'success' => 'green', 'danger' => 'red', default => 'amber' }">
                            {{ $p->match_status['label'] }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="xs" variant="ghost" wire:click="backfill({{ $p->client_id }})"
                            wire:confirm="{{ __('Create any missing monthly payment rows for this client?') }}">
                            {{ __('Backfill') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No subscription rows.') }}</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
