@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $btn = 'inline-flex items-center gap-1.5 rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5';
    $pill = fn ($label, $n, $tone) => $n > 0
        ? '<span class="rounded-full px-2 py-0.5 text-[11px] font-semibold '.$tone.'">'.number_format($n).' '.$label.'</span>'
        : '';
@endphp

<div class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Leads & Campaigns') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">
                {{ trans_choice(':count campaign|:count campaigns', $kpi['campaigns'], ['count' => number_format($kpi['campaigns'])]) }}
                · {{ number_format($kpi['leads']) }} {{ __('leads loaded') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (\Illuminate\Support\Facades\Route::has('automation.leads'))
                <a href="{{ route('automation.leads') }}" wire:navigate class="{{ $btn }}"><flux:icon name="chart-bar" variant="micro" class="size-4 text-violet-500" /> {{ __('Lead Analytics') }}</a>
            @endif
            <button type="button" wire:click="updateLeads" class="{{ $btn }}">
                <flux:icon name="arrow-path" variant="micro" class="size-4 text-amber-500" wire:loading.class="animate-spin" wire:target="updateLeads" /> {{ __('Update Leads') }}
            </button>
            <button type="button" wire:click="$set('showNew', true)" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <flux:icon name="plus" variant="micro" class="size-4" /> {{ __('New Campaign') }}
            </button>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Total Leads', number_format($kpi['leads']), 'users', 'text-blue-600'],
            ['Sales', number_format($kpi['sale']), 'check-circle', 'text-emerald-600'],
            ['Call Backs', number_format($kpi['call_back']), 'clock', 'text-violet-600'],
            ['No Status', number_format($kpi['no_status']), 'exclamation-circle', 'text-zinc-400'],
        ] as [$label, $value, $icon, $tone])
            <div class="{{ $card }}">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">
                    <flux:icon name="{{ $icon }}" variant="micro" class="size-4 {{ $tone }}" /> {{ __($label) }}
                </div>
                <div class="mt-1 text-2xl font-bold {{ $tone }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Search + filter --}}
    <div class="mt-5 flex flex-wrap items-center gap-3">
        <div class="relative max-w-sm flex-1">
            <flux:icon name="magnifying-glass" variant="outline" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search campaigns…') }}"
                class="w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] py-2.5 pl-10 pr-3 text-sm placeholder:text-zinc-400 focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
        </div>
        <div class="inline-flex rounded-lg border border-[var(--color-hairline)] p-0.5 text-sm font-medium">
            @foreach (['active' => __('Active'), 'all' => __('All'), 'inactive' => __('Inactive')] as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')"
                    @class(['rounded-md px-3 py-1.5 transition', 'bg-blue-600 text-white' => $filter === $key, 'text-zinc-500' => $filter !== $key])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Campaign cards --}}
    <div class="mt-5 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        @forelse ($campaigns as $c)
            @php($s = $c->stats)
            <div wire:key="camp-{{ $c->id }}" class="{{ $card }} flex flex-col">
                <div class="flex items-start justify-between">
                    <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">{{ $c->price_type ?: __('campaign') }}</span>
                    @if ((int) $c->status !== 1)
                        <span class="rounded-full bg-zinc-200 px-2 py-0.5 text-[10px] font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ __('Inactive') }}</span>
                    @endif
                </div>

                <h3 class="mt-2 text-lg font-bold text-[var(--color-navy-900)] dark:text-white">{{ $c->name ?: '#'.$c->id }}</h3>

                <div class="mt-3 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-sm font-bold text-[var(--color-navy-900)] dark:text-zinc-100">{{ number_format($s['total']) }}</span>
                        <span class="text-xs text-zinc-400">{{ __('leads') }}</span>
                        {!! $pill('Sale', $s['sale'], 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300') !!}
                        {!! $pill('Call Back', $s['call_back'], 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300') !!}
                        {!! $pill('No Ans.', $s['no_answer'], 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300') !!}
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        {!! $pill('No Status', $s['no_status'], 'bg-zinc-200 text-zinc-600 dark:bg-white/10 dark:text-zinc-300') !!}
                        @if ($s['conv'] > 0)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600"><flux:icon name="chart-bar" variant="micro" class="size-3.5" /> {{ $s['conv'] }}% {{ __('conv.') }}</span>
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-[var(--color-hairline)] pt-3 text-xs">
                    <span class="text-zinc-400">{{ __('Created') }} {{ optional($c->created_at)->format('M j, Y') ?? '—' }}</span>
                    <a href="{{ route('leads.campaign', $c) }}" wire:navigate class="inline-flex items-center gap-1 font-semibold text-blue-600 hover:underline">{{ __('View Leads') }} <flux:icon name="chevron-right" variant="micro" class="size-3.5" /></a>
                </div>
            </div>
        @empty
            <div class="{{ $card }} col-span-full py-16 text-center text-sm text-zinc-500">{{ __('No campaigns match.') }}</div>
        @endforelse
    </div>

    {{-- New Campaign modal --}}
    <flux:modal wire:model="showNew" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('New Campaign') }}</flux:heading>
            <flux:input wire:model="newName" :label="__('Campaign name')" />
            <flux:input wire:model="newSubtitle" :label="__('Subtitle (optional)')" />
            <flux:select wire:model="newType" :label="__('Type')">
                <flux:select.option value="website">website</flux:select.option>
                <flux:select.option value="cold_blast">cold_blast</flux:select.option>
                <flux:select.option value="transfer">transfer</flux:select.option>
                <flux:select.option value="import">import</flux:select.option>
            </flux:select>
            @error('newName') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showNew', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="createCampaign">{{ __('Create') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
