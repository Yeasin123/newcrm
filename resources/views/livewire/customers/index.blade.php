@php
    $planLabel = function ($c) {
        if ((string) $c->payment_structure === '1') {
            return 'Monthly';
        }
        $label = 'Yearly';
        if ($c->start_date && $c->end_date) {
            try {
                $months = \Illuminate\Support\Carbon::parse($c->start_date)->diffInMonths(\Illuminate\Support\Carbon::parse($c->end_date));
                $years = (int) round($months / 12);
                if ($years >= 1) {
                    $label = $years.' '.\Illuminate\Support\Str::plural('Year', $years);
                }
            } catch (\Throwable) {
            }
        }
        if (filled($c->installment) && (int) $c->installment > 1) {
            $label .= ' ('.$c->installment.' installments)';
        }

        return $label;
    };

    $th = 'px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-400';
    $td = 'px-4 py-3 align-top';
@endphp

<div class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Customers') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">
                <span wire:loading.remove wire:target="search, segment, salesman, loadMore">{{ number_format($totalCount) }}</span>
                <span wire:loading wire:target="search, segment, salesman, loadMore">…</span>
                {{ __('customers found') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="$refresh"
                class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                <flux:icon name="arrow-path" variant="micro" class="size-4" wire:loading.class="animate-spin" wire:target="$refresh" /> {{ __('Refresh') }}
            </button>
            <a href="{{ route('customers.export', $this->exportParams) }}"
                class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                <flux:icon name="arrow-down-tray" variant="micro" class="size-4" /> {{ __('Export CSV') }}
            </a>
            <a href="{{ route('customers.import') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">
                <flux:icon name="arrow-up-tray" variant="micro" class="size-4" /> {{ __('Import CSV') }}
            </a>
            <a href="{{ route('sale-sheet.index') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <flux:icon name="plus" variant="micro" class="size-4" /> {{ __('Add Customer') }}
            </a>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative mt-5 max-w-xl">
        <flux:icon name="magnifying-glass" variant="outline" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
        <input type="text" wire:model.live.debounce.350ms="search" placeholder="{{ __('Search name, email, policy, city…') }}"
            class="w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] py-2.5 pl-10 pr-10 text-sm placeholder:text-zinc-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
        <flux:icon name="viewfinder-circle" variant="outline" class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
    </div>

    {{-- Tabs --}}
    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 border-b border-[var(--color-hairline)] text-sm">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="setSegment('{{ $key }}')" wire:key="tab-{{ $key }}"
                @class([
                    '-mb-px border-b-2 py-2.5 font-medium transition',
                    'border-blue-600 text-blue-600' => $segment === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $segment !== $key,
                ])>
                {{ $label }} <span class="text-zinc-400">({{ number_format($counts[$key] ?? 0) }})</span>
            </button>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="mt-4 overflow-x-auto rounded-xl border border-[var(--color-hairline)] bg-[var(--color-surface)]">
        <table class="w-full min-w-[1100px] text-sm">
            <thead class="border-b border-[var(--color-hairline)]">
                <tr>
                    <th class="{{ $th }} w-10">#</th>
                    <th class="{{ $th }} cursor-pointer" wire:click="sortBy('created_at')">{{ __('Date') }}</th>
                    <th class="{{ $th }}">{{ __('Customer') }}</th>
                    <th class="{{ $th }} cursor-pointer" wire:click="sortBy('policy_number')">{{ __('Policy #') }}</th>
                    <th class="{{ $th }}">{{ __('Package') }}</th>
                    <th class="{{ $th }}">{{ __('Plan') }}</th>
                    <th class="{{ $th }}">{{ __('Price') }}</th>
                    <th class="{{ $th }} cursor-pointer" wire:click="sortBy('status')">{{ __('Status') }}</th>
                    <th class="{{ $th }}">{{ __('Auth status') }}</th>
                    <th class="{{ $th }}">{{ __('Salesman') }}</th>
                    <th class="{{ $th }}">{{ __('Notes') }}</th>
                    <th class="{{ $th }} text-center">{{ __('Signed') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-hairline)]">
                @forelse ($customers as $customer)
                    <tr wire:key="cust-{{ $customer->id }}"
                        onclick="Livewire.navigate('{{ route('customers.show', $customer) }}')"
                        class="cursor-pointer hover:bg-black/[0.02] dark:hover:bg-white/5">
                        <td class="{{ $td }} text-zinc-400">{{ $loop->iteration }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-zinc-500">{{ optional($customer->created_at)->format('M j, Y') ?? '—' }}</td>
                        <td class="{{ $td }}">
                            <div class="flex items-center gap-2">
                                @if ($customer->star)
                                    <flux:icon name="star" variant="solid" class="size-4 shrink-0 text-amber-400" />
                                @endif
                                <div>
                                    <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $customer->full_name ?: '—' }}</div>
                                    <div class="text-xs text-zinc-500">{{ $customer->email }}</div>
                                    <div class="text-xs text-zinc-400">{{ $customer->contact }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="{{ $td }} whitespace-nowrap">
                            <div class="font-medium text-blue-600 underline decoration-blue-600/30">{{ $customer->policy_number ?: '—' }}</div>
                            <div class="text-xs text-zinc-400 underline decoration-zinc-300">{{ $customer->contact }}</div>
                        </td>
                        <td class="{{ $td }}">
                            @if ($customer->propertyPackage?->title)
                                <span class="inline-block rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">{{ $customer->propertyPackage->title }}</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="{{ $td }} whitespace-nowrap">
                            <span class="inline-block rounded-full border border-[var(--color-hairline)] px-2.5 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $planLabel($customer) }}</span>
                        </td>
                        <td class="{{ $td }} whitespace-nowrap font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">
                            @php($ph = $customer->latestEligiblePolicyPaymentHistory)
                            @if ($ph && $ph->amount !== null)
                                ${{ number_format((float) $ph->amount, 2) }}<span class="text-xs font-normal text-zinc-400">{{ (string) $customer->payment_structure === '1' ? '/mo' : '' }}</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="{{ $td }}">
                            @php($sv = (int) $customer->status)
                            <span @class([
                                'inline-block rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $sv === 1,
                                'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => $sv === 0,
                                'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $sv === 5,
                            ])>{{ $sv === 1 ? __('Active') : ($sv === 5 ? __('Bypass') : __('Suspended')) }}</span>
                        </td>
                        <td class="{{ $td }}">
                            @if (filled($customer->authorize_status))
                                <span class="inline-block rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ ucfirst($customer->authorize_status) }}</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="{{ $td }} whitespace-nowrap text-zinc-600 dark:text-zinc-300">{{ $customer->salesman_name ?: '—' }}</td>
                        <td class="{{ $td }} max-w-[14rem] truncate text-zinc-500">{{ \Illuminate\Support\Str::limit($customer->description, 40) }}</td>
                        <td class="{{ $td }} text-center">
                            @if (filled($customer->signature_date_time))
                                <flux:icon name="check-circle" variant="solid" class="mx-auto size-5 text-emerald-500" />
                            @else
                                <flux:icon name="x-circle" variant="solid" class="mx-auto size-5 text-red-400" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="px-4 py-16 text-center text-zinc-500">{{ __('No customers match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Infinite scroll sentinel --}}
    @if ($hasMore)
        <div wire:key="more-{{ $perPage }}" x-data x-init="new IntersectionObserver(([e]) => e.isIntersecting && $wire.loadMore(), { rootMargin: '600px' }).observe($el)"
            class="py-6 text-center text-sm text-zinc-400">
            <span wire:loading.remove wire:target="loadMore">{{ __('Scroll for more…') }}</span>
            <span wire:loading wire:target="loadMore">{{ __('Loading…') }}</span>
        </div>
    @elseif ($atCap)
        <p class="py-6 text-center text-sm text-zinc-400">{{ __('Showing the first :n. Use search or a tab to narrow the list.', ['n' => number_format($perPage)]) }}</p>
    @endif
</div>
