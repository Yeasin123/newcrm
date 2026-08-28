@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[10px] font-semibold uppercase tracking-wide text-zinc-400';
    $money = fn ($n) => '$'.number_format((float) $n, 2);
@endphp

<div class="w-full">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">
                <flux:icon name="arrow-path" variant="mini" class="size-6 text-blue-600" />
                {{ __('Renewal Queue') }}
            </h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Customers expiring within ~4 months — flag early, renew often') }}</p>
        </div>
        <button type="button" wire:click="startBoard"
            class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-navy-900)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
            <flux:icon name="play" variant="micro" class="size-4" /> {{ __('Start Yearly Board') }}
        </button>
    </div>

    {{-- KPIs --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="{{ $card }}">
            <div class="flex items-center gap-2"><flux:icon name="users" variant="micro" class="size-4 text-blue-500" /><span class="{{ $lbl }}">{{ __('Up for renewal') }}</span></div>
            <div class="mt-2 text-3xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ number_format($kpi['total']) }}</div>
            <div class="text-[11px] text-zinc-400">{{ $kpi['yearly'] }} {{ __('yearly') }} · {{ $kpi['monthly'] }} {{ __('monthly') }}</div>
        </div>
        <div class="{{ $card }} border-rose-200 dark:border-rose-500/30">
            <div class="flex items-center gap-2"><flux:icon name="exclamation-circle" variant="micro" class="size-4 text-rose-500" /><span class="{{ $lbl }} text-rose-500">{{ __('Critical (≤7 days)') }}</span></div>
            <div class="mt-2 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($kpi['critical']) }}</div>
            <div class="text-[11px] text-zinc-400">{{ __('need immediate contact') }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="flex items-center gap-2"><flux:icon name="check-circle" variant="micro" class="size-4 text-emerald-500" /><span class="{{ $lbl }} text-emerald-500">{{ __('Contacted') }}</span></div>
            <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($kpi['contacted']) }}</div>
            <div class="text-[11px] text-zinc-400">{{ __('today') }}</div>
        </div>
        <div class="{{ $card }}">
            <div class="flex items-center gap-2"><flux:icon name="arrow-trending-up" variant="micro" class="size-4 text-violet-500" /><span class="{{ $lbl }} text-violet-500">{{ __('Value at stake') }}</span></div>
            <div class="mt-2 text-3xl font-bold text-violet-600 dark:text-violet-400">{{ $money($kpi['yearly_value']) }}</div>
            <div class="text-[11px] text-zinc-400">{{ __('annualised across the window') }}</div>
        </div>
    </div>

    {{-- Tabs + search --}}
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex rounded-xl border border-[var(--color-hairline)] p-1 text-sm font-semibold">
            <button type="button" wire:click="setTab('yearly')" @class(['rounded-lg px-4 py-2 transition', 'bg-blue-600 text-white' => $tab === 'yearly', 'text-zinc-500' => $tab !== 'yearly'])>
                {{ __('Yearly Renewals') }} <span class="ml-1 rounded-full bg-black/10 px-1.5 text-xs dark:bg-white/15">{{ $kpi['yearly'] }}</span>
            </button>
            <button type="button" wire:click="setTab('monthly')" @class(['rounded-lg px-4 py-2 transition', 'bg-blue-600 text-white' => $tab === 'monthly', 'text-zinc-500' => $tab !== 'monthly'])>
                {{ __('Monthly Renewals') }} <span class="ml-1 rounded-full bg-black/10 px-1.5 text-xs dark:bg-white/15">{{ $kpi['monthly'] }}</span>
            </button>
        </div>
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Name, policy, email, phone…')" class="max-w-xs" />
    </div>

    {{-- List --}}
    <div class="{{ $card }} mt-4">
        <div class="flex items-center justify-between">
            <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white">
                <flux:icon name="calendar-days" variant="micro" class="size-4 text-zinc-400" />
                {{ $tab === 'monthly' ? __('Monthly Contract Renewals') : __('Yearly Contract Renewals') }}
            </h2>
            <span class="text-xs text-zinc-400">{{ number_format($rows->total()) }} {{ __('customers') }}</span>
        </div>

        <div class="mt-3 divide-y divide-[var(--color-hairline)]">
            @forelse ($rows as $r)
                @php
                    $critical = $r->days_left <= 7;
                    $contacted = $contactedIds->contains($r->id);
                @endphp
                <div class="flex flex-wrap items-center gap-4 py-3" wire:key="rn-{{ $r->id }}">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-blue-600/10 text-xs font-bold text-blue-700 dark:text-blue-300">
                        {{ \Illuminate\Support\Str::of($r->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                    </div>
                    <div class="min-w-[12rem] flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-[var(--color-navy-900)] dark:text-white">{{ $r->name }}</span>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => $critical,
                                'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => ! $critical,
                            ])>{{ $r->days_left }}d {{ __('left') }}</span>
                            @if ($contacted)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('Contacted') }}</span>
                            @endif
                        </div>
                        <div class="mt-0.5 truncate text-xs text-zinc-500">
                            {{ $r->email ?: '—' }} · {{ $r->contact ?: '—' }}{{ $r->city ? ' · '.$r->city : '' }}
                        </div>
                    </div>
                    <div class="hidden text-xs sm:block"><div class="{{ $lbl }}">{{ __('Plan') }}</div><div class="font-semibold">{{ $r->term_label }}</div></div>
                    <div class="hidden text-xs md:block"><div class="{{ $lbl }}">{{ __('Package') }}</div><div class="font-semibold">{{ $r->package }}</div></div>
                    <div class="text-xs"><div class="{{ $lbl }}">{{ __('Annual') }}</div><div class="font-semibold">{{ $money($r->annual) }}</div></div>
                    <div class="hidden text-xs lg:block"><div class="{{ $lbl }}">{{ __('Expires') }}</div><div class="font-semibold">{{ $r->expires->format('M j, Y') }}</div></div>
                    <div class="flex items-center gap-2">
                        @unless ($contacted)
                            <flux:button size="xs" variant="ghost" wire:click="markContacted({{ $r->id }})">{{ __('Contacted') }}</flux:button>
                        @endunless
                        <a href="{{ route('renewals.board', ['client' => $r->id, 'filter' => $tab]) }}" wire:navigate
                            class="inline-flex items-center gap-1 rounded-lg bg-[var(--color-navy-900)] px-3 py-1.5 text-xs font-semibold text-white transition hover:opacity-90">
                            <flux:icon name="play" variant="micro" class="size-3.5" /> {{ __('Board') }}
                        </a>
                        <a href="{{ route('customers.show', $r->id) }}" wire:navigate class="text-zinc-300 hover:text-zinc-500">
                            <flux:icon name="chevron-right" variant="micro" class="size-4" />
                        </a>
                    </div>
                </div>
            @empty
                <div class="py-10 text-center text-sm text-zinc-400">{{ __('No policies renew in this window.') }}</div>
            @endforelse
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</div>
