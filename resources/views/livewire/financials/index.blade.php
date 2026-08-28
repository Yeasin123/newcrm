@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[10px] font-semibold uppercase tracking-wide text-zinc-400';
    $money = fn ($n) => ($n < 0 ? '-$' : '$').number_format(abs((float) $n), 2);
@endphp

<div class="w-full">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">
                <flux:icon name="chart-bar-square" variant="mini" class="size-6 text-blue-600" /> {{ __('Financials') }}
            </h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Net P&L — Authorize.net gross minus Zen fees, reserve holds, and chargebacks') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-zinc-400">{{ __('Not auto-syncing') }}</span>
            <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="refresh">{{ __('Refresh') }}</flux:button>
            <flux:button size="sm" variant="primary" icon="arrows-right-left" wire:click="syncZen">{{ __('Sync All Zen Data') }}</flux:button>
        </div>
    </div>

    {{-- Range --}}
    <div class="mt-4 inline-flex rounded-xl border border-[var(--color-hairline)] p-1 text-sm font-semibold">
        @foreach (['this_month' => __('This Month'), 'last_month' => __('Last Month'), 'custom' => __('Custom')] as $key => $label)
            <button type="button" wire:click="setRange('{{ $key }}')" @class(['rounded-lg px-4 py-2', 'bg-blue-600 text-white' => $range === $key, 'text-zinc-500' => $range !== $key])>{{ $label }}</button>
        @endforeach
    </div>
    @if ($range === 'custom')
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <flux:input type="date" wire:model.live="from" class="max-w-40" />
            <flux:input type="date" wire:model.live="to" class="max-w-40" />
        </div>
    @endif
    <p class="mt-1 text-xs text-zinc-400">{{ $periodLabel }}</p>

    {{-- KPI cards --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Gross Revenue') }}</div><div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $money($metrics['gross']) }}</div></div>
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Processing Fees') }}</div><div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $money($metrics['fees']) }}</div></div>
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Net Reserves') }}</div><div class="mt-1 text-2xl font-bold text-violet-600 dark:text-violet-400">{{ $money($metrics['reserves']) }}</div></div>
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Chargebacks') }}</div><div class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ $money($metrics['chargebacks']) }}</div></div>
        <div class="{{ $card }} {{ $metrics['net'] < 0 ? 'border-rose-300 dark:border-rose-500/40' : 'border-emerald-300 dark:border-emerald-500/40' }}">
            <div class="{{ $lbl }}">{{ __('Net Profit') }}</div>
            <div class="mt-1 text-2xl font-bold {{ $metrics['net'] < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $money($metrics['net']) }}</div>
            <div class="text-[11px] text-zinc-400">{{ __('Gross − Refunds − Fees − Reserves − CBs') }}</div>
        </div>
    </div>

    {{-- Chart --}}
    <div class="{{ $card }} mt-5">
        <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Net Profit by Month') }}</h2>
        <p class="text-xs text-zinc-500">{{ __('Last 6 months — all time zones UTC') }}</p>
        <x-chart type="bars" :labels="$chart['labels']"
            :series="[['name' => __('Net Profit'), 'data' => $chart['data'], 'color' => '#10b981']]" :height="220" class="mt-3" />
    </div>

    {{-- Statement tabs --}}
    <div class="mt-5 flex flex-wrap gap-1 border-b border-[var(--color-hairline)] pb-px text-sm font-semibold">
        @foreach (['statements' => __('Statements'), 'reserves' => __('Reserves'), 'payouts' => __('Payouts'), 'authorizations' => __('Authorizations')] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'rounded-t-lg px-4 py-2 transition',
                'border-b-2 border-blue-600 text-blue-600' => $tab === $key,
                'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="{{ $card }} mt-4 py-16 text-center">
        <flux:icon name="clock" variant="outline" class="mx-auto size-8 text-zinc-300" />
        <p class="mt-3 font-semibold text-[var(--color-navy-900)] dark:text-white">{{ __('No :tab for this period', ['tab' => strtolower($tab)]) }}</p>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Processor statement data appears here once the Zen feed is connected.') }}</p>
    </div>
</div>
