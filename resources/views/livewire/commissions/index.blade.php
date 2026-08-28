@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[11px] font-semibold uppercase tracking-wide text-zinc-400';
@endphp

<div class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Commissions') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Company-wide commission tracking — next pay:') }} <span class="font-semibold text-amber-500">{{ $overview['next_payday']->format('M j, Y') }}</span></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="runPayroll" wire:confirm="{{ __('Run payroll for the current pay period across all salesmen?') }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                <flux:icon name="play" variant="micro" class="size-4" /> {{ __('Run Payroll') }}
            </button>
            <button type="button" wire:click="$toggle('showSchedule')"
                class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-amber-400 px-3.5 py-2 text-sm font-semibold text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10">
                <flux:icon name="bolt" variant="micro" class="size-4" /> {{ __('Schedule 2×') }}
            </button>
            <a href="{{ route('commissions.settings') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                <flux:icon name="cog-6-tooth" variant="micro" class="size-4" /> {{ __('Commission Settings') }}
            </a>
            <a href="{{ route('salesmen.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-blue-600 hover:underline">{{ __('View Salesmen') }} <flux:icon name="chevron-right" variant="micro" class="size-3.5" /></a>
        </div>
    </div>

    {{-- Schedule 2× banner --}}
    @if ($showSchedule)
        <div class="mt-5 rounded-2xl border border-amber-400/60 bg-amber-50/50 p-5 dark:bg-amber-500/5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 font-bold text-amber-700 dark:text-amber-300"><flux:icon name="bolt" variant="solid" class="size-4" /> {{ __('Schedule a one-time 2× commission event') }}</h2>
                    <p class="mt-0.5 text-xs text-amber-700/80 dark:text-amber-200/70">{{ __('Choose one window, such as 6:00–7:00 PM today. Both boards play five gongs at the start and again at the end.') }}</p>
                </div>
                <button type="button" wire:click="clearSchedule" class="text-amber-500 hover:text-amber-700"><flux:icon name="x-mark" variant="micro" class="size-5" /></button>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div><label class="{{ $lbl }}">{{ __('Date') }}</label><input type="date" wire:model="sxDate" class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm dark:bg-[var(--color-surface-2)] dark:text-zinc-100"></div>
                <div><label class="{{ $lbl }}">{{ __('Start Time') }}</label><input type="time" wire:model="sxStart" class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm dark:bg-[var(--color-surface-2)] dark:text-zinc-100"></div>
                <div><label class="{{ $lbl }}">{{ __('End Time') }}</label><input type="time" wire:model="sxEnd" class="mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm dark:bg-[var(--color-surface-2)] dark:text-zinc-100"></div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <p class="text-xs text-zinc-400">{{ __("Times use this computer's local timezone: :tz. This does not repeat automatically.", ['tz' => config('app.timezone')]) }}</p>
                <button type="button" wire:click="saveSchedule" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">
                    <flux:icon name="bolt" variant="micro" class="size-4" /> {{ __('Save one-time schedule') }}
                </button>
            </div>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['PENDING '.strtoupper($overview['next_payday']->format('M j, Y')), '$'.number_format($overview['pending'], 2), 'all salesmen combined', 'clock', 'text-amber-500'],
            ['TOTAL PAID ALL-TIME', '$'.number_format($overview['paid_all_time'], 2), trans_choice(':n payroll run|:n payroll runs', $overview['payroll_runs'], ['n' => $overview['payroll_runs']]), 'currency-dollar', 'text-violet-500'],
            ['ACTIVE SALESMEN', number_format($overview['active_salesmen']), __('of :n total', ['n' => $overview['total_salesmen']]), 'users', 'text-blue-500'],
            ['CLAWBACKS PENDING', '-$'.number_format($overview['clawbacks_pending'], 2), 'deductions next pay', 'exclamation-triangle', 'text-red-500'],
        ] as [$k, $v, $sub, $icon, $tone])
            <div class="{{ $card }}">
                <div class="flex items-center gap-2">
                    <div class="grid size-9 place-items-center rounded-lg bg-black/5 {{ $tone }} dark:bg-white/10"><flux:icon name="{{ $icon }}" variant="micro" class="size-4" /></div>
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ $k }}</span>
                </div>
                <div class="mt-2 text-2xl font-bold {{ $tone }}">{{ $v }}</div>
                <div class="text-[11px] text-zinc-400">{{ $sub }}</div>
            </div>
        @endforeach
    </div>

    {{-- Salesman breakdown --}}
    <div class="{{ $card }} mt-5 !p-0 overflow-hidden">
        <div class="border-b border-[var(--color-hairline)] p-4">
            <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Salesman Breakdown') }}</h2>
            <p class="text-xs text-zinc-400">{{ __('Click a row to view the full commission detail') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="border-b border-[var(--color-hairline)] text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                        <th>{{ __('Salesman') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Sales') }}</th><th class="text-right">{{ __('Est. Payout') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-hairline)]">
                    @foreach ($breakdown as $b)
                        <tr wire:key="brk-{{ $b->user->id }}" onclick="Livewire.navigate('{{ route('salesmen.show', $b->user) }}')"
                            class="cursor-pointer [&>td]:px-4 [&>td]:py-3 hover:bg-black/[0.02] dark:hover:bg-white/5">
                            <td class="flex items-center gap-2">
                                <span class="grid size-7 place-items-center rounded-lg bg-blue-600 text-[10px] font-bold text-white">{{ $b->user->initials() }}</span>
                                <span class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $b->user->name }}</span>
                            </td>
                            <td class="text-emerald-600">{{ rtrim(rtrim(number_format($b->rate, 2), '0'), '.') }}%</td>
                            <td class="font-semibold">{{ $b->sales ?: '—' }}</td>
                            <td class="text-right font-bold {{ $b->est_payout > 0 ? 'text-amber-500' : 'text-zinc-400' }}">${{ number_format($b->est_payout, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-[var(--color-hairline)] text-sm font-bold">
                    <tr class="[&>td]:px-4 [&>td]:py-3">
                        <td class="uppercase tracking-wide text-zinc-400">{{ __('Company Totals') }}</td>
                        <td></td>
                        <td>{{ number_format($companyTotals['sales']) }}</td>
                        <td class="text-right text-amber-500">${{ number_format($companyTotals['payout'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Weekly Billing Spliffy --}}
    @if ($spliffy['enabled'])
        <div class="{{ $card }} mt-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="trophy" variant="micro" class="size-4 text-amber-500" /> {{ __('Weekly Billing Spliffy') }} <span class="text-xs font-normal text-zinc-400">· {{ $spliffy['week_start']->format('M j') }} – {{ $spliffy['week_end']->format('M j, Y') }}</span></h2>
                <span class="rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">${{ number_format($spliffy['amount'], 0) }} {{ __('award') }}</span>
            </div>
            <div class="mt-3 space-y-1.5">
                @foreach ($spliffy['rows']->take(13) as $r)
                    <div wire:key="sp-{{ $r->user->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-[var(--color-hairline)] px-3 py-2 text-sm {{ $loop->first && $r->charged > 0 ? 'bg-amber-50/60 dark:bg-amber-500/5' : '' }}">
                        <div class="flex items-center gap-3">
                            <span class="w-5 text-center text-xs font-bold text-zinc-400">{{ $loop->iteration }}</span>
                            <span class="grid size-7 place-items-center rounded-lg bg-blue-600 text-[10px] font-bold text-white">{{ $r->user->initials() }}</span>
                            <span class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r->user->name }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-bold text-[var(--color-navy-900)] dark:text-zinc-100">${{ number_format($r->charged, 2) }}</span>
                            @if ($loop->first && $r->charged > 0)
                                <button type="button" wire:click="awardBillingSpliffy" wire:confirm="{{ __('Award the $:n Billing Spliffy to :name?', ['n' => number_format($spliffy['amount'], 0), 'name' => $r->user->name]) }}"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2.5 py-0.5 text-[10px] font-bold text-white hover:bg-emerald-700">
                                    {{ __('Award $:n', ['n' => number_format($spliffy['amount'], 0)]) }} <flux:icon name="chevron-right" variant="micro" class="size-3" />
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-zinc-400">{{ __('Top biller charges the most this week — award the $:n bonus from the settings page. Bypass sales do not count.', ['n' => number_format($spliffy['amount'], 0)]) }}</p>
        </div>
    @endif

    {{-- Clawback detail --}}
    <div class="{{ $card }} mt-5 !p-0 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--color-hairline)] p-4">
            <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white">
                <flux:icon name="receipt-refund" variant="micro" class="size-4 text-red-500" /> {{ __('Cancelled & Disputed — Clawback Detail') }}
                <span class="text-xs font-normal text-zinc-400">({{ $clawbacks['rows']->count() }} {{ __('within :n days', ['n' => $clawbacks['window_days']]) }})</span>
            </h2>
            <span class="text-xs text-zinc-400">{{ __('Deducted from the next payout automatically.') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="border-b border-[var(--color-hairline)] text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-2.5 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                        <th>{{ __('Customer') }}</th><th>{{ __('Salesman') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Signed Up') }}</th><th>{{ __('Days In') }}</th><th class="text-right">{{ __('Commission') }}</th><th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-hairline)]">
                    @forelse ($clawbacks['rows'] as $r)
                        @php($signedUp = optional($r->client?->created_at))
                        <tr wire:key="cb-{{ $r->id }}" class="[&>td]:px-4 [&>td]:py-2.5">
                            <td>
                                <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r->client?->full_name ?: '—' }}</div>
                                <div class="text-xs text-zinc-400">{{ $r->policy_number }}</div>
                            </td>
                            <td class="text-zinc-600 dark:text-zinc-300">{{ $r->salesman?->name ?: __('Unknown') }}</td>
                            <td>
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-300">{{ filled($r->client?->disputed) ? __('Disputed') : __('Cancelled') }}</span>
                            </td>
                            <td class="whitespace-nowrap text-zinc-500">{{ $signedUp?->format('Y-m-d') ?? '—' }}</td>
                            <td class="text-zinc-500">{{ $signedUp ? (int) $signedUp->diffInDays($r->updated_at).'d' : '—' }}</td>
                            <td class="whitespace-nowrap text-right font-semibold text-red-600">-${{ number_format((float) $r->commision_price, 2) }}</td>
                            <td><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ __('Pending') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No pending clawbacks.') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($clawbacks['rows']->isNotEmpty())
                    <tfoot class="border-t border-[var(--color-hairline)] text-sm font-bold">
                        <tr class="[&>td]:px-4 [&>td]:py-3"><td colspan="6" class="text-right uppercase tracking-wide text-zinc-400">{{ __('Total Pending Clawbacks') }}</td><td class="text-red-600">-${{ number_format($clawbacks['total'], 2) }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Payroll history --}}
    <div class="{{ $card }} mt-5">
        <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="calendar-days" variant="micro" class="size-4" /> {{ __('Payroll History') }}</h2>
        <div class="mt-3 space-y-2">
            @forelse ($payroll['list'] as $h)
                <div wire:key="ph-{{ $h->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--color-hairline)] p-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="grid size-7 place-items-center rounded-lg bg-blue-600 text-[10px] font-bold text-white">{{ $h->salesman?->initials() ?? '—' }}</span>
                        <div>
                            <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $h->salesman?->name ?? __('Unknown') }}</div>
                            <div class="text-xs text-zinc-400">{{ __('Period') }} {{ $h->period_start->format('M j') }} – {{ $h->period_end->format('M j, Y') }} · {{ __('Paid') }} {{ $h->pay_date->format('M j, Y') }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-5 text-xs">
                        <span><span class="font-bold text-[var(--color-navy-900)] dark:text-zinc-100">{{ number_format($h->sales_count) }}</span> {{ __('sales') }}</span>
                        <span class="font-bold text-emerald-600">${{ number_format((float) $h->net, 2) }}</span>
                        <span class="text-zinc-400">{{ __('by') }} {{ $h->run_by_email ?: ($h->runBy?->email ?? '—') }}</span>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-zinc-500">{{ __('No payroll runs recorded yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
