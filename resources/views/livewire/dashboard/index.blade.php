@php
    $s = $m['scalars'];
    $f = $m['monthly_financials'];
    $fc = $m['financial_chart'];
    $oc = $m['operational_chart'];
    $money = fn ($v) => '$'.number_format((float) $v, 2);
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Welcome back, :name.', ['name' => auth()->user()->first_name ?: auth()->user()->name]) }}
                &middot; {{ $f['period'] }}
            </flux:text>
        </div>
        <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="refresh">{{ __('Refresh') }}</flux:button>
    </div>

    {{-- KPI tiles --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            [__('Active customers'), number_format($s['active_customers']), __(':n total', ['n' => number_format($s['clients'])])],
            [__('This month earnings'), $money($s['month_earnings']), __('Today :v', ['v' => $money($s['todays_earnings'])])],
            [__('Estimated net (mo.)'), $money($f['estimated_net']), __('Gross :v', ['v' => $money($f['gross_revenue'])])],
            [__('Collection rate'), $f['collection_rate'].'%', __(':a attempts', ['a' => number_format($f['payment_attempts'])])],
            [__('Open leads'), number_format($s['leads']), __(':n new today', ['n' => $s['new_clients_today']])],
            [__('Unsuccessful payments'), number_format($s['unsuccessful_payment_clients']), __(':n disputes', ['n' => $s['dispute_clients']])],
            [__('Cancelled policies'), number_format($s['cancelled_policies']), __(':n refunded', ['n' => $s['refunded_clients']])],
            [__('Expected earnings'), $money($s['expected_earnings']), __('Year :v', ['v' => $money($s['year_earnings'])])],
        ] as [$label, $value, $sub])
            <flux:card class="p-5">
                <flux:text class="text-xs text-zinc-500">{{ $label }}</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $value }}</flux:heading>
                <flux:text class="mt-1 text-xs text-zinc-400">{{ $sub }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Revenue — last 12 months') }}</flux:heading>
            <x-chart class="mt-3" type="bars" :labels="$fc['labels']" :series="[
                ['name' => __('Gross revenue'), 'data' => $fc['gross_revenue']],
                ['name' => __('Refunds'), 'data' => $fc['refunds']],
                ['name' => __('Claim costs'), 'data' => $fc['claim_costs']],
            ]" />
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Customers — new vs cancelled') }}</flux:heading>
            <x-chart class="mt-3" type="line" :labels="$oc['customer_trend']['labels']" :series="[
                ['name' => __('New'), 'data' => $oc['customer_trend']['new_customers']],
                ['name' => __('Cancelled'), 'data' => $oc['customer_trend']['cancelled_customers'], 'color' => '#ef4444'],
            ]" />
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Claim status mix') }}</flux:heading>
            <x-chart class="mt-3" type="donut" :labels="$oc['claim_status']['labels']" :series="[['name' => '', 'data' => $oc['claim_status']['values']]]" />
        </flux:card>

        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Lead funnel — :p', ['p' => $oc['lead_funnel']['period']]) }}</flux:heading>
            <x-chart class="mt-3" type="bars" :labels="$oc['lead_funnel']['labels']" :series="[['name' => '', 'data' => $oc['lead_funnel']['values']]]" />
        </flux:card>
    </div>

    {{-- salesman performance --}}
    <flux:card class="p-5">
        <flux:heading size="sm">{{ __('Salesman performance — :p', ['p' => $oc['salesman_performance']['period']]) }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Salesman') }}</flux:table.column>
                <flux:table.column>{{ __('New customers') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($oc['salesman_performance']['labels'] as $i => $name)
                    <flux:table.row :key="'sp-'.$i">
                        <flux:table.cell variant="strong">{{ $name }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($oc['salesman_performance']['values'][$i] ?? 0) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2" class="text-center text-zinc-500">{{ __('No sales recorded this month.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
