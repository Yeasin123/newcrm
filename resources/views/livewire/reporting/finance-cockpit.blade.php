@php $money = fn ($v) => ($v < 0 ? '-$' : '$').number_format(abs((float) $v), 2); @endphp

<div class="flex h-full w-full flex-1 flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Finance Cockpit') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ $range['from']->format('M Y') }} – {{ $range['to']->format('M Y') }}
            </flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="months" class="max-w-[9rem]">
                @foreach ([1 => '1 month', 3 => '3 months', 6 => '6 months', 12 => '12 months', 24 => '24 months'] as $v => $l)
                    <flux:select.option :value="$v">{{ $l }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="flex gap-2">
        @foreach (['overview' => __('Overview'), 'monthly' => __('Monthly'), 'salesmen' => __('Salesmen'), 'clients' => __('Clients'), 'ledger' => __('Ledger')] as $key => $label)
            <flux:button size="sm" :variant="$tab === $key ? 'primary' : 'ghost'" wire:click="$set('tab', '{{ $key }}')" wire:key="t-{{ $key }}">{{ $label }}</flux:button>
        @endforeach
    </div>

    @if ($tab === 'overview' || $tab === 'ledger')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                [__('Gross revenue'), $money($summary['gross_revenue'])],
                [__('Refunds'), $money($summary['refunds'])],
                [__('Disputes'), $money($summary['disputes'])],
                [__('Claim cost'), $money($summary['claim_costs'])],
                [__('Commission expense'), $money($summary['commission_expense'])],
                [__('Estimated net'), $money($summary['estimated_net'])],
                [__('New customers'), number_format($summary['new_customers'])],
                [__('Payment attempts'), number_format($summary['payment_attempts'])],
            ] as [$label, $value])
                <flux:card class="p-5">
                    <flux:text class="text-xs text-zinc-500">{{ $label }}</flux:text>
                    <flux:heading size="lg" class="mt-1">{{ $value }}</flux:heading>
                </flux:card>
            @endforeach
        </div>
    @endif

    @if ($tab === 'ledger')
        <flux:card class="p-5">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Line') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($ledgerRows as $row)
                        <flux:table.row :key="'l-'.$loop->index">
                            <flux:table.cell variant="strong">{{ $row['label'] }}</flux:table.cell>
                            <flux:table.cell class="{{ $row['amount'] < 0 ? 'text-red-600' : '' }}">{{ $money($row['amount']) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'overview' || $tab === 'monthly')
        <flux:card class="p-5">
            <flux:heading size="sm">{{ __('Monthly net') }}</flux:heading>
            <x-chart class="mt-3" type="bars"
                :labels="collect($monthlySeries)->pluck('label')->all()"
                :series="[
                    ['name' => __('Gross'), 'data' => collect($monthlySeries)->pluck('gross_revenue')->all()],
                    ['name' => __('Estimated net'), 'data' => collect($monthlySeries)->pluck('estimated_net')->all(), 'color' => '#10b981'],
                    ['name' => __('Commission'), 'data' => collect($monthlySeries)->pluck('commission_expense')->all(), 'color' => '#f59e0b'],
                ]" />
        </flux:card>

        @if ($tab === 'monthly')
            <flux:card class="p-5">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Month') }}</flux:table.column>
                        <flux:table.column>{{ __('Gross') }}</flux:table.column>
                        <flux:table.column>{{ __('Refunds') }}</flux:table.column>
                        <flux:table.column>{{ __('Commission') }}</flux:table.column>
                        <flux:table.column>{{ __('Net') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($monthlySeries as $row)
                            <flux:table.row :key="'m-'.$row['period']">
                                <flux:table.cell variant="strong">{{ $row['label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $money($row['gross_revenue']) }}</flux:table.cell>
                                <flux:table.cell>{{ $money($row['refunds']) }}</flux:table.cell>
                                <flux:table.cell>{{ $money($row['commission_expense']) }}</flux:table.cell>
                                <flux:table.cell>{{ $money($row['estimated_net']) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    @endif

    @if ($tab === 'salesmen')
        <flux:card class="p-5">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Salesman') }}</flux:table.column>
                    <flux:table.column>{{ __('Sales') }}</flux:table.column>
                    <flux:table.column>{{ __('Billed') }}</flux:table.column>
                    <flux:table.column>{{ __('Commission') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($salesmen as $s)
                        <flux:table.row :key="'s-'.$s->id">
                            <flux:table.cell variant="strong">{{ trim($s->first_name.' '.$s->last_name) }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($s->sales_count) }}</flux:table.cell>
                            <flux:table.cell>{{ $money($s->billed_total) }}</flux:table.cell>
                            <flux:table.cell>{{ $money($s->commission_total) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No data.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'clients')
        <flux:card class="p-5">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Invoices') }}</flux:table.column>
                    <flux:table.column>{{ __('Revenue') }}</flux:table.column>
                    <flux:table.column>{{ __('Refunds') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($clients as $c)
                        <flux:table.row :key="'c-'.$c->client_id">
                            <flux:table.cell variant="strong">
                                @if ($c->userInfo)
                                    <a class="hover:underline" wire:navigate href="{{ route('customers.show', $c->userInfo) }}">
                                        {{ trim($c->userInfo->first_name.' '.$c->userInfo->last_name) ?: $c->userInfo->email }}
                                    </a>
                                @else
                                    {{ __('Client #').$c->client_id }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ number_format($c->invoice_count) }}</flux:table.cell>
                            <flux:table.cell>{{ $money($c->revenue_total) }}</flux:table.cell>
                            <flux:table.cell>{{ $money($c->refund_total) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('No data.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
