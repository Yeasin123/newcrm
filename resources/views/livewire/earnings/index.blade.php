@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[10px] font-semibold uppercase tracking-wide text-zinc-400';
    $money = fn ($n) => '$'.number_format((float) $n, 2);
    $kpi = function ($k, $v, $sub, $icon, $tone) use ($card) {
        return '<div class="'.$card.'"><div class="flex items-center gap-2"><div class="grid size-9 place-items-center rounded-lg bg-black/5 '.$tone.' dark:bg-white/10"></div><span class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">'.$k.'</span></div><div class="mt-2 text-2xl font-bold '.$tone.'">'.$v.'</div><div class="text-[11px] text-zinc-400">'.$sub.'</div></div>';
    };
@endphp

<div class="w-full">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Earnings') }}</h1>
        <p class="mt-0.5 text-sm text-zinc-500">{{ __('Revenue metrics, commission expense, and net earnings across all salesmen') }}</p>
    </div>

    {{-- Tabs --}}
    <div class="mt-4 inline-flex flex-wrap rounded-xl border border-[var(--color-hairline)] p-1 text-sm font-semibold">
        @foreach (['overview' => ['Overview', 'arrow-trending-up'], 'revenue' => ['Revenue', 'chart-bar'], 'commissions' => ['Commissions', 'currency-dollar'], 'salesman' => ['By Salesman', 'trophy']] as $key => [$label, $icon])
            <button type="button" wire:click="setTab('{{ $key }}')"
                @class(['inline-flex items-center gap-1.5 rounded-lg px-4 py-2 transition', 'bg-blue-600 text-white' => $tab === $key, 'text-zinc-500' => $tab !== $key])>
                <flux:icon name="{{ $icon }}" variant="micro" class="size-4" /> {{ __($label) }}
            </button>
        @endforeach
    </div>

    {{-- ============================ OVERVIEW ============================ --}}
    @if ($tab === 'overview')
        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Revenue') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('Based on currently active customer policies') }}</p>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {!! $kpi(__('Gross MRR'), $money($overview['gross_mrr']), $overview['active_customers'].' '.__('active customers'), 'arrow-trending-up', 'text-blue-500') !!}
            {!! $kpi(__('Gross ARR'), $money($overview['gross_arr']), __('MRR × 12'), 'chart-bar', 'text-violet-500') !!}
            {!! $kpi(__('Active Customers'), number_format($overview['active_customers']), $overview['total_policies'].' '.__('total policies'), 'users', 'text-emerald-500') !!}
            {!! $kpi(__('Avg per Customer'), $money($overview['avg_per_customer']), __('monthly / active'), 'currency-dollar', 'text-blue-500') !!}
        </div>

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Recurring Revenue Breakdown') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('Monthly cash collected each month — subscribers vs. installment payers') }}</p>
        <div class="mt-3 grid gap-4 lg:grid-cols-3">
            {!! $kpi(__('Monthly Subscriber MRR'), $money($overview['monthly_mrr']), $overview['monthly_count'].' '.__('month-to-month customers'), 'arrow-trending-up', 'text-blue-500') !!}
            {!! $kpi(__('Installment Recovery MRR'), $money($overview['inst_mrr']), $overview['inst_count'].' '.__('plans paying in installments'), 'rectangle-stack', 'text-violet-500') !!}
            {!! $kpi(__('Total Recurring MRR'), $money($overview['recurring_mrr']), __('monthly + installment combined'), 'arrow-trending-up', 'text-emerald-500') !!}
        </div>

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Net After Commissions') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('Commission expense deducted from gross revenue') }}</p>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {!! $kpi(__('Commissions Paid'), $money($overview['commissions_paid']), $overview['payroll_runs'].' '.__('payroll runs'), 'currency-dollar', 'text-amber-500') !!}
            {!! $kpi(__('Pending Commissions'), $money($overview['commissions_pending']), __('not yet paid out'), 'wallet', 'text-amber-500') !!}
            {!! $kpi(__('Commission Rate'), $overview['commission_rate'].'%', __('of gross ARR'), 'percent-badge', 'text-violet-500') !!}
            {!! $kpi(__('Net ARR'), $money($overview['net_arr']), $overview['net_margin'].'% '.__('net margin'), 'arrow-trending-up', 'text-emerald-500') !!}
        </div>

        {{-- Snapshot --}}
        <div class="{{ $card }} mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Earnings Snapshot') }}</h2>
                <div class="inline-flex rounded-lg border border-[var(--color-hairline)] p-0.5 text-xs font-semibold">
                    <button type="button" wire:click="$set('snapMode', 'cash')" @class(['rounded-md px-3 py-1', 'bg-blue-600 text-white' => $snapMode === 'cash', 'text-zinc-500' => $snapMode !== 'cash'])>{{ __('Cash Collected') }}</button>
                    <button type="button" wire:click="$set('snapMode', 'full')" @class(['rounded-md px-3 py-1', 'bg-blue-600 text-white' => $snapMode === 'full', 'text-zinc-500' => $snapMode !== 'full'])>{{ __('Full Contract') }}</button>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-1 text-xs font-medium">
                @foreach (['last_2_weeks' => __('Last 2 Weeks'), 'this_month' => __('This Month'), 'last_month' => __('Last Month'), 'last_3_months' => __('Last 3 Months'), 'last_6_months' => __('Last 6 Months'), 'all_time' => __('All Time')] as $key => $label)
                    <button type="button" wire:click="$set('snapRange', '{{ $key }}')" @class(['rounded-full px-3 py-1 transition', 'bg-blue-600 text-white' => $snapRange === $key, 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $snapRange !== $key])>{{ $label }}</button>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-zinc-400">{{ __('Revenue = first payment collected per new customer (installment amount for split plans, monthly charge for monthly plans). Commissions = payroll actually paid out in this period.') }}</p>

            <div class="mt-4 grid gap-4 text-center sm:grid-cols-3">
                <div><div class="{{ $lbl }}">{{ __('Cash Collected') }}</div><div class="mt-1 text-3xl font-bold text-blue-500">{{ $money($snapshot['revenue']) }}</div><div class="text-xs text-zinc-400">{{ $snapshot['new_customers'] }} {{ __('new customers') }}</div></div>
                <div><div class="{{ $lbl }}">− {{ __('Commissions Paid') }}</div><div class="mt-1 text-3xl font-bold text-amber-500">{{ $money($snapshot['commissions']) }}</div><div class="text-xs text-zinc-400">{{ $snapshot['payroll_runs'] }} {{ __('payroll runs') }}</div></div>
                <div><div class="{{ $lbl }}">= {{ __('Net Earnings') }}</div><div class="mt-1 text-3xl font-bold text-emerald-500">{{ $money($snapshot['net']) }}</div><div class="text-xs text-zinc-400">{{ $snapshot['net_margin'] }}% {{ __('net margin') }}</div></div>
            </div>
            @php
                $commPct = $snapshot['revenue'] > 0 ? round($snapshot['commissions'] / $snapshot['revenue'] * 100) : 0;
            @endphp
            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-black/5 dark:bg-white/10">
                <div class="bg-emerald-500" style="width: {{ 100 - $commPct }}%"></div>
                <div class="bg-amber-500" style="width: {{ $commPct }}%"></div>
            </div>
            <div class="mt-1 flex gap-4 text-xs text-zinc-400">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> {{ __('Net') }} ({{ 100 - $commPct }}%)</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-amber-500"></span> {{ __('Commissions') }} ({{ $commPct }}%)</span>
            </div>
        </div>

        <div class="{{ $card }} mt-5">
            <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Commission Payouts by Month') }}</h2>
            <x-chart type="bars" :labels="$payouts['labels']" :series="[['name' => __('Payouts'), 'data' => $payouts['data'], 'color' => '#f59e0b']]" :height="200" class="mt-3" />
        </div>
    @endif

    {{-- ============================ REVENUE ============================ --}}
    @if ($tab === 'revenue')
        @php
            $planRows = collect($portfolio['plan'])->map(fn ($v, $k) => ['key' => $k, 'label' => $planLabel($k), 'count' => $v['count'], 'mrr' => $v['mrr']])->sortByDesc('mrr')->values();
            $planMrrTotal = $planRows->sum('mrr') ?: 1;
            $pkgRows = collect($portfolio['package'])->map(fn ($v, $k) => ['label' => $k, 'count' => $v['count'], 'mrr' => $v['mrr']])->sortByDesc('mrr')->values();
            $pkgMrrTotal = $pkgRows->sum('mrr') ?: 1;
            $statusOrder = ['active' => 'Active', 'cancelled' => 'Cancelled', 'suspended' => 'Suspended', 'bypass' => 'Bypass', 'failed' => 'Failed', 'renewal' => 'Renewal', 'inactive' => 'Inactive'];
            $statusTotal = collect($portfolio['status'])->sum('count') ?: 1;
        @endphp

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Revenue by Plan Type') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('Active customers only') }}</p>
        <div class="mt-3 grid gap-5 lg:grid-cols-2">
            <div class="{{ $card }}">
                <div class="{{ $lbl }}">{{ __('MRR Contribution') }}</div>
                <x-chart type="bars" :labels="$planRows->pluck('label')->all()" :series="[['name' => 'MRR', 'data' => $planRows->pluck('mrr')->map(fn ($v) => round($v))->all(), 'color' => '#8b5cf6']]" :height="240" class="mt-2" />
            </div>
            <div class="{{ $card }} !p-0 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="border-b border-[var(--color-hairline)] text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-2.5 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                            <th>{{ __('Plan') }}</th><th>{{ __('Customers') }}</th><th class="text-right">{{ __('MRR') }}</th><th class="text-right">{{ __('ARR') }}</th><th class="text-right">{{ __('% of MRR') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @foreach ($planRows as $r)
                            <tr class="[&>td]:px-4 [&>td]:py-2.5">
                                <td class="font-medium text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r['label'] }}</td>
                                <td>{{ number_format($r['count']) }}</td>
                                <td class="text-right font-semibold">{{ $money($r['mrr']) }}</td>
                                <td class="text-right text-zinc-500">{{ $money($r['mrr'] * 12) }}</td>
                                <td class="text-right text-blue-500">{{ round($r['mrr'] / $planMrrTotal * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-[var(--color-hairline)] text-sm font-bold">
                        <tr class="[&>td]:px-4 [&>td]:py-3"><td>{{ __('Total') }}</td><td>{{ number_format($planRows->sum('count')) }}</td><td class="text-right">{{ $money($planRows->sum('mrr')) }}</td><td class="text-right">{{ $money($planRows->sum('mrr') * 12) }}</td><td class="text-right">100%</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Revenue by Package') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('Package tier mix') }}</p>
        <div class="mt-3 grid gap-5 lg:grid-cols-2">
            <div class="{{ $card }}">
                <div class="{{ $lbl }}">{{ __('Package Mix') }}</div>
                <x-chart type="donut" :labels="$pkgRows->take(8)->pluck('label')->all()" :series="[['name' => 'MRR', 'data' => $pkgRows->take(8)->pluck('mrr')->map(fn ($v) => round($v))->all()]]" :height="220" class="mt-2" />
            </div>
            <div class="space-y-2">
                @foreach ($pkgRows->take(12) as $r)
                    <div class="{{ $card }} !p-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:icon name="rectangle-stack" variant="micro" class="size-4 text-violet-500" />
                            <div>
                                <div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r['label'] }}</div>
                                <div class="text-[11px] text-zinc-400">{{ number_format($r['count']) }} {{ __('customers') }} · {{ round($r['mrr'] / $pkgMrrTotal * 100, 1) }}% {{ __('of MRR') }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $money($r['mrr']) }}<span class="text-xs font-normal text-zinc-400">/mo</span></div>
                            <div class="text-[11px] text-zinc-400">{{ $money($r['mrr'] * 12) }}/yr</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Portfolio by Status') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('All customers regardless of active status') }}</p>
        <div class="{{ $card }} mt-3 !p-0 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="border-b border-[var(--color-hairline)] text-left">
                    <tr class="[&>th]:px-4 [&>th]:py-2.5 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                        <th>{{ __('Status') }}</th><th>{{ __('Customers') }}</th><th class="text-right">{{ __('% of Portfolio') }}</th><th class="text-right">{{ __('MRR (if active)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-hairline)]">
                    @foreach ($statusOrder as $key => $label)
                        @php
                            $s = $portfolio['status'][$key] ?? ['count' => 0, 'mrr' => 0];
                        @endphp
                        <tr class="[&>td]:px-4 [&>td]:py-2.5">
                            <td class="flex items-center gap-2 font-medium text-[var(--color-navy-900)] dark:text-zinc-100">
                                <span @class([
                                    'size-2 rounded-full',
                                    'bg-emerald-500' => $key === 'active', 'bg-red-500' => $key === 'cancelled',
                                    'bg-blue-500' => $key === 'suspended', 'bg-violet-500' => $key === 'bypass',
                                    'bg-amber-500' => $key === 'failed', 'bg-sky-500' => $key === 'renewal', 'bg-zinc-400' => $key === 'inactive',
                                ])></span> {{ $label }}
                            </td>
                            <td>{{ number_format($s['count']) }}</td>
                            <td class="text-right text-zinc-500">{{ round($s['count'] / $statusTotal * 100, 1) }}%</td>
                            <td class="text-right font-semibold">{{ $s['mrr'] > 0 ? $money($s['mrr']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-[var(--color-hairline)] text-sm font-bold">
                    <tr class="[&>td]:px-4 [&>td]:py-3"><td>{{ __('Total') }}</td><td>{{ number_format(collect($portfolio['status'])->sum('count')) }}</td><td class="text-right">100%</td><td class="text-right">{{ $money(collect($portfolio['status'])->sum('mrr')) }}</td></tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- ============================ COMMISSIONS ============================ --}}
    @if ($tab === 'commissions')
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {!! $kpi(__('Total Paid'), $money($totals['paid']), $totals['payroll_runs'].' '.__('payroll runs'), 'currency-dollar', 'text-emerald-500') !!}
            {!! $kpi(__('Pending Payout'), $money($totals['pending']), __('next pay cycle'), 'wallet', 'text-amber-500') !!}
            {!! $kpi(__('Pending Clawbacks'), '-'.$money($totals['clawbacks']), __('deducted from pending'), 'arrow-trending-down', 'text-red-500') !!}
            {!! $kpi(__('Total Expense'), $money($totals['paid'] + $totals['pending']), __('paid + pending'), 'minus-circle', 'text-violet-500') !!}
        </div>

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Commission by Salesman') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('Paid, pending, and clawbacks per rep') }}</p>
        <div class="{{ $card }} mt-3 !p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="border-b border-[var(--color-hairline)] text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-2.5 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                            <th>{{ __('Salesman') }}</th><th>{{ __('Rate') }}</th><th class="text-right">{{ __('Paid') }}</th><th class="text-right">{{ __('Pending') }}</th><th class="text-right">{{ __('Clawbacks') }}</th><th class="text-right">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @foreach ($rows as $r)
                            <tr class="[&>td]:px-4 [&>td]:py-2.5">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="grid size-7 place-items-center rounded-lg bg-blue-600 text-[10px] font-bold text-white">{{ $r->user->initials() }}</span>
                                        <div><div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r->user->name }}</div><div class="text-[11px] text-zinc-400">{{ number_format($r->customers_total) }} {{ __('customers') }}</div></div>
                                    </div>
                                </td>
                                <td class="text-zinc-500">{{ rtrim(rtrim(number_format(app(\App\Services\Commission\CommissionSettingsService::class)->rateFor($r->user->id), 2), '0'), '.') }}%</td>
                                <td class="text-right text-emerald-600">{{ $money($r->comm_paid) }}</td>
                                <td class="text-right text-amber-500">{{ $money($r->comm_pending) }}</td>
                                <td class="text-right text-zinc-400">—</td>
                                <td class="text-right font-bold">{{ $money($r->comm_paid + $r->comm_pending) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-[var(--color-hairline)] text-sm font-bold">
                        <tr class="[&>td]:px-4 [&>td]:py-3">
                            <td>{{ __('Total') }}</td><td></td>
                            <td class="text-right text-emerald-600">{{ $money($rows->sum('comm_paid')) }}</td>
                            <td class="text-right text-amber-500">{{ $money($rows->sum('comm_pending')) }}</td>
                            <td class="text-right text-zinc-400">—</td>
                            <td class="text-right">{{ $money($rows->sum('comm_paid') + $rows->sum('comm_pending')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Payroll History') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('All completed payroll runs') }}</p>
        <div class="{{ $card }} mt-3 !p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="border-b border-[var(--color-hairline)] text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-2.5 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                            <th>{{ __('Salesman') }}</th><th>{{ __('Pay Date') }}</th><th>{{ __('Period') }}</th><th class="text-right">{{ __('Gross') }}</th><th class="text-right">{{ __('Deductions') }}</th><th class="text-right">{{ __('Net Paid') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @forelse ($history as $h)
                            <tr class="[&>td]:px-4 [&>td]:py-2.5">
                                <td class="flex items-center gap-2">
                                    <span class="grid size-6 place-items-center rounded-lg bg-blue-600 text-[9px] font-bold text-white">{{ $h->salesman?->initials() ?? '?' }}</span>
                                    <span class="font-medium text-[var(--color-navy-900)] dark:text-zinc-100">{{ $h->salesman?->name ?? __('Unknown') }}</span>
                                </td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $h->pay_date->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap text-zinc-500">{{ $h->period_start->format('Y-m-d') }} → {{ $h->period_end->format('Y-m-d') }}</td>
                                <td class="text-right">{{ $money($h->gross) }}</td>
                                <td class="text-right text-zinc-400">—</td>
                                <td class="text-right font-semibold text-emerald-600">{{ $money($h->net) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500">{{ __('No payroll runs recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ============================ BY SALESMAN ============================ --}}
    @if ($tab === 'salesman')
        <div class="{{ $card }} mt-6">
            <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Revenue & Commissions by Salesman') }}</h2>
            <x-chart type="bars"
                :labels="$rows->take(10)->map(fn ($r) => $r->user->first_name)->all()"
                :series="[
                    ['name' => __('MRR'), 'data' => $rows->take(10)->pluck('mrr')->map(fn ($v) => round($v))->all(), 'color' => '#3b82f6'],
                    ['name' => __('Comm. Paid'), 'data' => $rows->take(10)->pluck('comm_paid')->map(fn ($v) => round($v))->all(), 'color' => '#f59e0b'],
                    ['name' => __('Net Rev'), 'data' => $rows->take(10)->map(fn ($r) => round($r->mrr * 12 - $r->comm_paid))->all(), 'color' => '#10b981'],
                ]" :height="260" class="mt-3" />
        </div>

        <h2 class="mt-6 font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Salesman Earnings Breakdown') }}</h2>
        <p class="text-sm text-zinc-500">{{ __('MRR from their customers vs. commissions earned') }}</p>
        <div class="{{ $card }} mt-3 !p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
                    <thead class="border-b border-[var(--color-hairline)] text-left">
                        <tr class="[&>th]:px-4 [&>th]:py-2.5 [&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                            <th>{{ __('Salesman') }}</th><th>{{ __('Customers') }}</th><th class="text-right">{{ __('MRR') }}</th><th class="text-right">{{ __('ARR') }}</th><th class="text-right">{{ __('Comm. Paid') }}</th><th class="text-right">{{ __('Comm. Pending') }}</th><th class="text-right">{{ __('Net ARR') }}</th><th class="text-right">{{ __('Net Margin') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @foreach ($rows as $r)
                            <tr class="[&>td]:px-4 [&>td]:py-2.5">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="grid size-7 place-items-center rounded-lg bg-blue-600 text-[10px] font-bold text-white">{{ $r->user->initials() }}</span>
                                        <div><div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r->user->name }}</div><div class="text-[11px] text-zinc-400">{{ rtrim(rtrim(number_format(app(\App\Services\Commission\CommissionSettingsService::class)->rateFor($r->user->id), 2), '0'), '.') }}% {{ __('rate') }} · {{ $r->user->status ? __('Active') : __('Inactive') }}</div></div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap text-zinc-500">{{ number_format($r->customers_active) }}/{{ number_format($r->customers_total) }}</td>
                                <td class="text-right font-semibold">{{ $money($r->mrr) }}</td>
                                <td class="text-right text-zinc-500">{{ $money($r->arr) }}</td>
                                <td class="text-right text-emerald-600">{{ $money($r->comm_paid) }}</td>
                                <td class="text-right text-amber-500">{{ $money($r->comm_pending) }}</td>
                                <td class="text-right font-bold">{{ $money($r->net_arr) }}</td>
                                <td class="text-right text-emerald-600">{{ $r->net_margin }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-[var(--color-hairline)] text-sm font-bold">
                        <tr class="[&>td]:px-4 [&>td]:py-3">
                            <td>{{ __('Company Total') }}</td>
                            <td>{{ number_format($rows->sum('customers_active')) }}/{{ number_format($rows->sum('customers_total')) }}</td>
                            <td class="text-right">{{ $money($rows->sum('mrr')) }}</td>
                            <td class="text-right">{{ $money($rows->sum('arr')) }}</td>
                            <td class="text-right text-emerald-600">{{ $money($rows->sum('comm_paid')) }}</td>
                            <td class="text-right text-amber-500">{{ $money($rows->sum('comm_pending')) }}</td>
                            <td class="text-right">{{ $money($rows->sum('net_arr')) }}</td>
                            <td class="text-right text-emerald-600">{{ $rows->sum('arr') > 0 ? round($rows->sum('net_arr') / $rows->sum('arr') * 100, 1) : 0 }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
