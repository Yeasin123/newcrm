@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[10px] font-semibold uppercase tracking-wide text-zinc-400';
    $tone = [
        'payment_collected' => 'text-emerald-600 bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-300',
        'renewed' => 'text-emerald-600 bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-300',
        'upgraded' => 'text-violet-600 bg-violet-100 dark:bg-violet-500/15 dark:text-violet-300',
        'spoke_with' => 'text-blue-600 bg-blue-100 dark:bg-blue-500/15 dark:text-blue-300',
        'call_back' => 'text-sky-600 bg-sky-100 dark:bg-sky-500/15 dark:text-sky-300',
        'no_answer' => 'text-amber-600 bg-amber-100 dark:bg-amber-500/15 dark:text-amber-300',
        'dispute' => 'text-rose-600 bg-rose-100 dark:bg-rose-500/15 dark:text-rose-300',
        'not_interested' => 'text-rose-600 bg-rose-100 dark:bg-rose-500/15 dark:text-rose-300',
        'skipped' => 'text-zinc-500 bg-zinc-100 dark:bg-white/10 dark:text-zinc-300',
        'other' => 'text-zinc-500 bg-zinc-100 dark:bg-white/10 dark:text-zinc-300',
    ];
@endphp

<div class="w-full">
    {{-- Full-bleed navy header --}}
    <div class="-m-4 mb-5 bg-[var(--color-navy-900)] text-white sm:-m-6 sm:mb-6 lg:-m-8 lg:mb-8">
        <div class="flex flex-wrap items-center gap-3 px-4 py-5 sm:px-6 lg:px-8">
            <span class="grid size-10 place-items-center rounded-xl bg-white/10"><flux:icon name="phone-arrow-up-right" variant="mini" class="size-5 text-blue-300" /></span>
            <div class="flex-1">
                <div class="text-lg font-bold">{{ __('Board History') }}</div>
                <div class="text-xs text-white/60">{{ __('All board interactions across agents') }}</div>
            </div>
            <span class="text-sm text-white/70">{{ number_format($kpi['total']) }} {{ $capped ? __('recent entries') : __('total entries') }}</span>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Total Calls') }}</div><div class="mt-1 text-3xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ number_format($kpi['total']) }}</div></div>
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Payments Collected') }}</div><div class="mt-1 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($kpi['collected']) }}</div><div class="text-[11px] text-zinc-400">{{ $kpi['success_rate'] }}% {{ __('success rate') }}</div></div>
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('No Answer') }}</div><div class="mt-1 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($kpi['no_answer']) }}</div><div class="text-[11px] text-zinc-400">{{ $kpi['no_answer_rate'] }}% {{ __('no-answer rate') }}</div></div>
        <div class="{{ $card }}"><div class="{{ $lbl }}">{{ __('Unique Agents') }}</div><div class="mt-1 text-3xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ number_format($kpi['agents']) }}</div><div class="text-[11px] text-zinc-400">{{ $kpi['top_agent'] ? __('Top: :name', ['name' => $kpi['top_agent']]) : '—' }}</div></div>
    </div>

    {{-- Outcomes breakdown --}}
    <div class="{{ $card }} mt-4">
        <div class="{{ $lbl }} mb-3">{{ __('Outcomes Breakdown') }}</div>
        <div class="flex flex-wrap gap-2">
            @foreach ($breakdown as $b)
                @php $pct = $kpi['total'] > 0 ? round($b['count'] / $kpi['total'] * 100) : 0; @endphp
                <button type="button" wire:click="$set('outcome', '{{ $outcome === $b['key'] ? 'all' : $b['key'] }}')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                        $tone[$b['key']],
                        'ring-2 ring-blue-500' => $outcome === $b['key'],
                        'opacity-40' => $b['count'] === 0 && $outcome !== $b['key'],
                    ])>
                    {{ $b['label'] }} <span class="font-bold">{{ $b['count'] }}</span>
                    @if ($b['count'] > 0)<span class="text-[10px] font-normal opacity-70">{{ $pct }}%</span>@endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- Agent leaderboard --}}
    <div class="{{ $card }} mt-4">
        <div class="{{ $lbl }} mb-3">{{ __('Agent Leaderboard') }}</div>
        @if ($leaderboard->isEmpty())
            <p class="text-sm text-zinc-400">{{ __('No agent activity in this range.') }}</p>
        @else
            <div class="space-y-2">
                @foreach ($leaderboard as $i => $a)
                    <div class="flex items-center gap-3 text-sm" wire:key="lb-{{ $i }}">
                        <span class="grid size-6 place-items-center rounded-full bg-amber-400 text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                        <span class="font-semibold text-[var(--color-navy-900)] dark:text-white">{{ $a['name'] }}</span>
                        <span class="text-zinc-400">{{ $a['calls'] }} {{ __('calls') }}</span>
                        <span class="text-emerald-600 dark:text-emerald-400">{{ $a['collected'] }} {{ __('collected') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="{{ $card }} mt-4">
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search by customer, agent, or note…')" class="min-w-[16rem] flex-1" />
            <flux:select wire:model.live="board" class="max-w-44">
                <flux:select.option value="all">{{ __('All Boards') }}</flux:select.option>
                <flux:select.option value="payment_failed">{{ __('Payment Failed') }}</flux:select.option>
                <flux:select.option value="renewals">{{ __('Renewals') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="outcome" class="max-w-44">
                <flux:select.option value="all">{{ __('All Outcomes') }}</flux:select.option>
                @foreach (\App\Livewire\BoardHistory\Index::OUTCOMES as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="agentScope" class="max-w-40">
                <flux:select.option value="all">{{ __('All Agents') }}</flux:select.option>
                <flux:select.option value="cs">{{ __('CS Agents Only') }}</flux:select.option>
            </flux:select>
            <span class="text-xs text-zinc-400">{{ number_format($rows->total()) }} {{ __('results') }}</span>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs font-medium">
            <flux:icon name="calendar-days" variant="micro" class="size-4 text-zinc-400" />
            @foreach (['today' => __('Today'), 'yesterday' => __('Yesterday'), 'week' => __('This Week'), 'month' => __('This Month'), 'all' => __('All Time')] as $key => $label)
                <button type="button" wire:click="setRange('{{ $key }}')"
                    @class(['rounded-full px-3 py-1 transition', 'bg-blue-600 text-white' => $range === $key, 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $range !== $key])>{{ $label }}</button>
            @endforeach
            <button type="button" wire:click="setRange('custom')" @class(['rounded-full px-3 py-1', 'bg-blue-600 text-white' => $range === 'custom', 'text-zinc-500' => $range !== 'custom'])>{{ __('Custom') }}</button>
            @if ($range === 'custom')
                <flux:input type="date" wire:model.live="from" size="sm" class="max-w-40" />
                <flux:input type="date" wire:model.live="to" size="sm" class="max-w-40" />
            @endif
        </div>

        {{-- Table --}}
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[60rem] text-sm">
                <thead>
                    <tr class="border-b border-[var(--color-hairline)] text-left {{ $lbl }}">
                        <th class="py-2 pr-3">{{ __('Date & Time') }}</th>
                        <th class="px-3">{{ __('Customer') }}</th>
                        <th class="px-3">{{ __('Board') }}</th>
                        <th class="px-3">{{ __('Outcome') }}</th>
                        <th class="px-3">{{ __('Agent') }}</th>
                        <th class="px-3">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-hairline)]">
                    @forelse ($rows as $r)
                        <tr wire:key="bh-{{ $r->id }}">
                            <td class="py-3 pr-3 whitespace-nowrap text-zinc-500">
                                {{ $r->at_label }}
                                <span class="block text-[11px] text-zinc-400">{{ $r->at_time }}</span>
                            </td>
                            <td class="px-3">
                                @if ($r->client)
                                    <a href="{{ route('customers.show', $r->client_id) }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-blue-600 hover:underline">
                                        {{ $r->client->full_name }} <flux:icon name="arrow-top-right-on-square" variant="micro" class="size-3" />
                                    </a>
                                @else
                                    <span class="text-zinc-400">#{{ $r->client_id }}</span>
                                @endif
                            </td>
                            <td class="px-3">
                                <span @class(['rounded-md px-2 py-0.5 text-[11px] font-semibold', 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => $r->board_key === 'payment_failed', 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' => $r->board_key === 'renewals'])>{{ $r->board_label }}</span>
                            </td>
                            <td class="px-3"><span class="rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $tone[$r->outcome] }}">{{ $r->outcome_label }}</span></td>
                            <td class="px-3 font-medium">{{ $r->agent?->name ?? '—' }}</td>
                            <td class="px-3 max-w-[24rem] truncate text-zinc-500">{{ $r->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-zinc-400">{{ __('No board interactions match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</div>
