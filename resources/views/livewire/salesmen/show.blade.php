@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $mini = 'rounded-xl border border-[var(--color-hairline)] p-4';
    $lbl = 'text-[11px] font-semibold uppercase tracking-wide text-zinc-400';
@endphp

<div class="w-full">
    {{-- Header --}}
    <div class="flex items-start gap-3">
        <a href="{{ route('salesmen.index') }}" wire:navigate class="mt-1 text-zinc-400 hover:text-blue-600"><flux:icon name="chevron-left" variant="micro" class="size-5" /></a>
        <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 text-sm font-bold text-white">{{ $salesman->initials() }}</div>
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">
                {{ $salesman->name }}@if ($view === 'all-sales') <span class="text-zinc-400">— {{ __('All Sales') }}</span>@endif
            </h1>
            <p class="mt-0.5 text-sm text-zinc-500">
                @if ($view === 'all-sales')
                    {{ __('Complete sales history · :rate% commission rate', ['rate' => rtrim(rtrim(number_format($rate * 100, 2), '0'), '.')]) }}
                @else
                    {{ __('Commission Tracking · :rate% rate · :label (next: :date)', [
                        'rate' => rtrim(rtrim(number_format($rate * 100, 2), '0'), '.'),
                        'label' => $periodLabel,
                        'date' => $nextPayday->format('M j, Y'),
                    ]) }}
                @endif
            </p>
        </div>
    </div>

    {{-- View toggle --}}
    <div class="mt-4 inline-flex rounded-xl border border-[var(--color-hairline)] p-1 text-sm font-semibold">
        @foreach (['today' => ['Today', 'bolt'], 'pay-period' => ['Pay Period', 'calendar-days'], 'all-sales' => ['All Sales', 'chart-bar']] as $key => [$label, $icon])
            <button type="button" wire:click="setView('{{ $key }}')"
                @class(['inline-flex items-center gap-1.5 rounded-lg px-4 py-2 transition', 'bg-blue-600 text-white' => $view === $key, 'text-zinc-500' => $view !== $key])>
                <flux:icon name="{{ $icon }}" variant="micro" class="size-4" /> {{ __($label) }}
            </button>
        @endforeach
    </div>

    {{-- ============================ TODAY ============================ --}}
    @if ($view === 'today')
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
            <div class="{{ $card }} flex items-center gap-4">
                <div class="grid size-11 place-items-center rounded-xl bg-blue-600 text-white"><flux:icon name="bolt" variant="solid" class="size-5" /></div>
                <div>
                    <div class="text-3xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ number_format($today['count']) }}</div>
                    <div class="text-xs text-zinc-500">{{ __('sales today') }}</div>
                </div>
            </div>
            <div class="{{ $card }} flex items-center gap-4">
                <div class="grid size-11 place-items-center rounded-xl bg-emerald-600 text-white"><flux:icon name="check-badge" variant="solid" class="size-5" /></div>
                <div>
                    <div class="text-3xl font-bold text-emerald-600">${{ number_format($today['commission'], 2) }}</div>
                    <div class="text-xs text-zinc-500">{{ __('commission earned') }}</div>
                </div>
            </div>
        </div>

        @if ($today['count'] === 0)
            <div class="{{ $card }} mt-5 flex flex-col items-center justify-center py-16 text-center lg:max-w-2xl">
                <flux:icon name="calendar-days" variant="outline" class="size-9 text-zinc-300" />
                <h3 class="mt-3 font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('No sales yet today') }}</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Sales you close today will appear here instantly.') }}</p>
            </div>
        @endif
    @endif

    {{-- ========================= PAY PERIOD ========================= --}}
    @if ($view === 'pay-period')
        {{-- Current period --}}
        <div class="mt-5 rounded-2xl border-2 border-amber-400/60 bg-amber-50/40 p-5 dark:bg-amber-500/5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <div class="grid size-9 place-items-center rounded-lg bg-amber-500 text-white"><flux:icon name="arrow-path" variant="micro" class="size-4" /></div>
                    <div>
                        <div class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Current Pay Period') }}</div>
                        <div class="text-xs text-zinc-500">{{ $period['start']->format('M j, Y') }} – {{ $period['end']->format('M j, Y') }}</div>
                    </div>
                </div>
                <span class="rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                    {{ __('Payday in :n days — :date', ['n' => max(0, (int) round(now()->diffInDays($period['payday'], false))), 'date' => $period['payday']->format('M j, Y')]) }}
                </span>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Deals This Period', number_format($period['deals']), ''],
                    ['Gross Earned', '$'.number_format($period['gross'], 2), ''],
                    ['Spliffy', '+$'.number_format($period['spiff'], 2), 'text-violet-600'],
                    ['Est. Payout', '$'.number_format($period['est_net'], 2), 'text-amber-600'],
                ] as [$k, $v, $tone])
                    <div class="{{ $mini }} bg-[var(--color-surface)]">
                        <div class="{{ $lbl }}">{{ __($k) }}</div>
                        <div class="mt-1 text-xl font-bold {{ $tone ?: 'text-[var(--color-navy-900)] dark:text-zinc-100' }}">{{ $v }}</div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-zinc-400">{{ __('Commissions reset every :label period when paid — next reset after :date.', ['label' => strtolower($periodLabel), 'date' => $period['payday']->format('M j, Y')]) }}</p>
        </div>

        {{-- Admin insights --}}
        <div class="{{ $card }} mt-5">
            <div class="flex items-center gap-2">
                <flux:icon name="chart-bar" variant="micro" class="size-4 text-blue-500" />
                <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Admin Insights') }}</h2>
                <span class="text-xs text-zinc-400">{{ __('all time · :n customers', ['n' => number_format($insights['customers'])]) }}</span>
            </div>

            <div class="{{ $lbl }} mt-4">{{ __('Plans Sold') }}</div>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($insights['plans'] as $label => $n)
                    <div class="{{ $mini }} text-center">
                        <div class="text-lg font-bold text-[var(--color-navy-900)] dark:text-zinc-100">{{ number_format($n) }}</div>
                        <div class="text-[11px] text-zinc-500">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 grid gap-6 sm:grid-cols-2">
                <div>
                    <div class="{{ $lbl }}">{{ __('Customer Status') }}</div>
                    <dl class="mt-2 space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Active') }}</dt><dd class="font-semibold text-emerald-600">{{ number_format($insights['status']['active']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Cancelled') }} <span class="ml-1 rounded bg-red-100 px-1.5 text-[10px] font-semibold text-red-600 dark:bg-red-500/15">{{ $insights['cancel_rate'] }}% {{ __('rate') }}</span></dt><dd class="font-semibold text-red-600">{{ number_format($insights['status']['cancelled']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Failed Billing') }}</dt><dd class="font-semibold">{{ number_format($insights['status']['failed']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Renewal') }}</dt><dd class="font-semibold text-violet-600">{{ number_format($insights['status']['renewal']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Bypass') }}</dt><dd class="font-semibold text-amber-600">{{ number_format($insights['status']['bypass']) }}</dd></div>
                    </dl>
                </div>
                <div>
                    <div class="{{ $lbl }}">{{ __('Payment & Earnings') }}</div>
                    <dl class="mt-2 space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Paid in Full') }}</dt><dd class="font-semibold">{{ number_format($insights['paid_in_full']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('On Installments') }}</dt><dd class="font-semibold">{{ number_format($insights['on_installments']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Payrolls Run') }}</dt><dd class="font-semibold">{{ number_format($insights['payrolls_run']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('All-Time Gross') }}</dt><dd class="font-semibold text-emerald-600">${{ number_format($insights['all_time_gross'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('All-Time Net Paid') }}</dt><dd class="font-semibold text-emerald-600">${{ number_format($insights['all_time_net'], 2) }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Next pay period --}}
        <div class="mt-5 overflow-hidden rounded-2xl border-2 border-amber-400/60">
            <div class="bg-amber-100 px-4 py-2 text-[11px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                {{ __('Next Pay Period · :date', ['date' => $period['payday']->format('M j, Y')]) }}
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-hairline)] bg-[var(--color-surface)] p-4 text-sm">
                <div>
                    <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $period['start']->format('M j, Y') }} – {{ $period['end']->format('M j, Y') }}</div>
                    <div class="text-xs text-zinc-400">{{ trans_choice(':n unpaid sale|:n unpaid sales', $period['deals'], ['n' => $period['deals']]) }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-5">
                    <div class="text-right"><div class="font-bold text-emerald-600">+${{ number_format($period['gross'], 2) }}</div><div class="text-[10px] text-zinc-400">{{ __('Gross') }}</div></div>
                    <div class="text-right"><div class="font-bold text-violet-600">+${{ number_format($period['spiff'], 2) }}</div><div class="text-[10px] text-zinc-400">{{ __('Spliffy') }}</div></div>
                    <div class="text-right"><div class="font-bold text-amber-600">${{ number_format($period['est_net'], 2) }}</div><div class="text-[10px] text-zinc-400">{{ __('Est. Net') }}</div></div>
                    <button type="button" wire:click="markPaid" wire:confirm="{{ __('Mark this pay period paid? This records a payroll run and flips the sales to paid.') }}"
                        @disabled($period['deals'] === 0)
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                        <flux:icon name="check-circle" variant="micro" class="size-4" /> {{ __('Mark Paid') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto bg-[var(--color-surface)]">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="border-b border-[var(--color-hairline)] text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-2.5 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                            <th>{{ __('Customer') }}</th><th>{{ __('Policy #') }}</th><th>{{ __('Date & Time') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Sale Value') }}</th><th>{{ __('Commission') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @forelse ($period['rows'] as $r)
                            <tr wire:key="pp-{{ $r->id }}" class="[&>td]:px-4 [&>td]:py-2.5">
                                <td>
                                    <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r->client?->full_name ?: '—' }}</div>
                                    @if ($r->client)<span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ (int) $r->client->status === 1 ? __('Active') : __('Inactive') }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($r->client_id)<a href="{{ route('customers.show', $r->client_id) }}" wire:navigate class="text-blue-600 underline decoration-blue-600/30">{{ $r->policy_number ?: '—' }}</a>@else {{ $r->policy_number ?: '—' }} @endif
                                </td>
                                <td class="whitespace-nowrap text-zinc-500">{{ optional($r->store_date)->format('M j · g:i A') ?? '—' }}</td>
                                <td class="whitespace-nowrap">
                                    {{ app(\App\Services\Commission\SalesmanCommissionService::class)->planLabel($r) }}
                                    @if ((int) ($r->installment ?? 0) < 2 && (string) $r->payment_structure !== '1')<span class="ml-1 rounded bg-emerald-100 px-1 text-[9px] font-bold text-emerald-700 dark:bg-emerald-500/15">PIF</span>@endif
                                </td>
                                <td class="whitespace-nowrap font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">${{ number_format((float) $r->price, 2) }}</td>
                                <td class="whitespace-nowrap font-semibold text-emerald-600">+${{ number_format((float) $r->commision_price, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No unpaid sales in this pay period.') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($period['rows']->isNotEmpty())
                        <tfoot class="border-t border-[var(--color-hairline)] text-sm">
                            <tr class="[&>td]:px-4 [&>td]:py-2"><td colspan="6" class="text-right text-violet-600">{{ __('Spliffy') }}: +${{ number_format($period['spiff'], 2) }}</td></tr>
                            <tr class="[&>td]:px-4 [&>td]:pb-3"><td colspan="6" class="text-right font-bold text-amber-600">{{ __('Estimated Net Payout') }}: ${{ number_format($period['est_net'], 2) }}</td></tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Payroll history --}}
        <div class="{{ $card }} mt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="calendar-days" variant="micro" class="size-4" /> {{ __('Payroll History') }}</h2>
                <div class="flex flex-wrap gap-1 text-xs font-medium">
                    @foreach (['all' => __('All Time'), 'this_period' => __('This Period'), 'this_month' => __('This Month'), 'last_month' => __('Last Month'), 'last_3_months' => __('Last 3 Months')] as $key => $label)
                        <button type="button" wire:click="$set('historyRange', '{{ $key }}')"
                            @class(['rounded-full px-3 py-1 transition', 'bg-blue-600 text-white' => $historyRange === $key, 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $historyRange !== $key])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="mt-3 space-y-2">
                @forelse ($history as $h)
                    <div wire:key="pr-{{ $h->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--color-hairline)] p-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('PAID') }}</span>
                            <div>
                                <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Pay Date') }}: {{ $h->pay_date->format('M j, Y') }}</div>
                                <div class="text-xs text-zinc-400">{{ $h->period_start->format('M j, Y') }} – {{ $h->period_end->format('M j, Y') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-5 text-xs">
                            <span><span class="font-bold text-[var(--color-navy-900)] dark:text-zinc-100">{{ number_format($h->sales_count) }}</span> {{ __('Sales') }}</span>
                            <span class="font-bold text-emerald-600">${{ number_format((float) $h->net, 2) }} <span class="font-normal text-zinc-400">{{ __('Net Paid') }}</span></span>
                            <span class="text-zinc-400">{{ __('by') }} {{ $h->run_by_email ?: ($h->runBy?->email ?? '—') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-zinc-500">{{ __('No payroll runs recorded for this range.') }}</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ========================= ALL SALES ========================= --}}
    @if ($view === 'all-sales')
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                [number_format($all['total_customers']), 'Total Customers', 'users', 'text-blue-600'],
                [$all['total_customers'] ? number_format($all['active']).' ('.round($all['active'] / max(1, $all['total_customers']) * 100).'%)' : '0', 'Active', 'check-circle', 'text-emerald-600'],
                [$all['total_customers'] ? number_format($all['cancelled']).' ('.round($all['cancelled'] / max(1, $all['total_customers']) * 100).'%)' : '0', 'Cancelled', 'x-circle', 'text-red-600'],
                ['$'.number_format($all['all_time_gross'], 2), 'All-Time Gross Paid', 'currency-dollar', 'text-violet-600'],
                ['$'.number_format($all['all_time_net'], 2), 'All-Time Net Paid', 'banknotes', 'text-emerald-600'],
                ['$'.number_format($all['avg_plan_value'], 2), 'Avg Plan Value', 'arrow-trending-up', 'text-blue-600'],
            ] as [$v, $k, $icon, $tone])
                <div class="{{ $card }} flex items-center gap-4">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-black/5 {{ $tone }} dark:bg-white/10"><flux:icon name="{{ $icon }}" variant="micro" class="size-5" /></div>
                    <div>
                        <div class="text-xl font-bold {{ $tone }}">{{ $v }}</div>
                        <div class="text-xs text-zinc-500">{{ __($k) }}@if($k === 'All-Time Gross Paid') · {{ trans_choice(':n payroll run|:n payrolls run', $all['payrolls_run'], ['n' => $all['payrolls_run']]) }}@elseif($k === 'Avg Plan Value') · {{ __('yearly plans only') }}@endif</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div class="{{ $card }}">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="chart-bar" variant="micro" class="size-4" /> {{ __('Monthly Sales Volume') }}</h2>
                    <span class="text-xs text-zinc-400">{{ __('last 12 months') }}</span>
                </div>
                <x-chart type="bars" :labels="$all['chart_labels']" :series="[['name' => __('Sales'), 'data' => $all['chart_data'], 'color' => '#3b82f6']]" :height="200" class="mt-3" />
            </div>
            <div class="{{ $card }}">
                <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="chart-pie" variant="micro" class="size-4" /> {{ __('Plan Mix') }}</h2>
                <div class="mt-3 flex flex-wrap items-center gap-6">
                    <x-chart type="donut" :labels="array_keys($all['plan_mix'])" :series="[['name' => __('Plans'), 'data' => array_values($all['plan_mix'])]]" :height="180" />
                    <dl class="flex-1 space-y-1.5 text-sm">
                        @php($mixTotal = array_sum($all['plan_mix']) ?: 1)
                        @foreach ($all['plan_mix'] as $label => $n)
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-zinc-500">{{ $label }}</dt>
                                <dd class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $n }} <span class="ml-1 text-xs font-normal text-zinc-400">{{ round($n / $mixTotal * 100) }}%</span></dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>

        <div class="{{ $card }} mt-5">
            <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="rectangle-stack" variant="micro" class="size-4" /> {{ __('Customer Status Breakdown') }}</h2>
            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                <span class="rounded-lg bg-emerald-50 px-3 py-1.5 font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ number_format($all['active']) }} {{ __('Active') }} <span class="font-normal">({{ $all['total_customers'] ? round($all['active'] / $all['total_customers'] * 100) : 0 }}%)</span></span>
                <span class="rounded-lg bg-red-50 px-3 py-1.5 font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ number_format($all['cancelled']) }} {{ __('Cancelled') }} <span class="font-normal">({{ $all['total_customers'] ? round($all['cancelled'] / $all['total_customers'] * 100) : 0 }}%)</span></span>
            </div>
        </div>

        <div class="{{ $card }} mt-5 !p-0 overflow-hidden">
            <div class="flex items-center justify-between border-b border-[var(--color-hairline)] p-4">
                <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="clipboard-document-list" variant="micro" class="size-4" /> {{ __('All Sales') }}</h2>
                <span class="text-xs text-zinc-400">{{ trans_choice(':n customer|:n customers', $all['rows']->count(), ['n' => $all['rows']->count()]) }} · {{ __('most recent first') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="border-b border-[var(--color-hairline)] text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                            <th>{{ __('Customer') }}</th><th>{{ __('Policy #') }}</th><th>{{ __('Sale Date') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Value') }}</th><th>{{ __('Commission') }}</th><th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @forelse ($all['rows'] as $r)
                            <tr wire:key="as-{{ $r->id }}" class="[&>td]:px-4 [&>td]:py-3">
                                <td>
                                    <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r->client?->full_name ?: '—' }}</div>
                                    <div class="text-xs text-zinc-400">{{ $r->client?->email }}</div>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($r->client_id)<a href="{{ route('customers.show', $r->client_id) }}" wire:navigate class="text-blue-600 underline decoration-blue-600/30">{{ $r->policy_number ?: '—' }}</a>@else {{ $r->policy_number ?: '—' }} @endif
                                </td>
                                <td class="whitespace-nowrap text-zinc-500">{{ optional($r->store_date)->format('M j, Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ app(\App\Services\Commission\SalesmanCommissionService::class)->planLabel($r) }}</td>
                                <td class="whitespace-nowrap font-semibold">${{ number_format((float) $r->price, 2) }}{{ (string) $r->payment_structure === '1' ? '/mo' : '' }}</td>
                                <td class="whitespace-nowrap font-semibold text-emerald-600">${{ number_format((float) $r->commision_price, 2) }}</td>
                                <td>
                                    @php($cs = (int) ($r->client?->status ?? -1))
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $cs === 1,
                                        'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => $cs === 0 || ($r->client && $r->client->login_off),
                                        'bg-zinc-200 text-zinc-500 dark:bg-white/10' => $cs === -1,
                                    ])>{{ $cs === 1 ? __('Active') : ($cs === -1 ? '—' : __('Cancelled')) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No sales recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
