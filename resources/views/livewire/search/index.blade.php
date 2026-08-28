@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'block text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100';
    $hint = 'mt-1 text-xs text-zinc-400';
    $inp = 'mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm placeholder:text-zinc-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:bg-[var(--color-surface-2)] dark:text-zinc-100';
@endphp

<div class="w-full">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Search') }}</h1>
        <p class="mt-0.5 text-sm text-zinc-500">{{ __('Find customers and leads across your entire CRM.') }}</p>
    </div>

    {{-- Smart Search --}}
    <div class="mt-5 rounded-2xl border border-violet-300/50 bg-violet-50 p-5 dark:border-violet-500/25 dark:bg-violet-500/10">
        <div class="flex items-center gap-2">
            <flux:icon name="sparkles" variant="solid" class="size-5 text-violet-500" />
            <span class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Smart Search') }}</span>
            <span class="rounded-full bg-violet-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700 dark:bg-violet-500/20 dark:text-violet-200">{{ __('AI-powered') }}</span>
        </div>
        <p class="mt-1 text-xs text-violet-700/80 dark:text-violet-200/70">
            {{ __('Try: "Jeff\'s failed customers" or "Kitchen plan customers in Nevada" or "Max\'s monthly accounts"') }}
        </p>
        <div class="relative mt-3">
            <flux:icon name="magnifying-glass" variant="outline" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-zinc-400" />
            <input type="text" wire:model="smart" wire:keydown.enter.prevent="runSmart"
                placeholder="{{ __('Ask anything about your customers…') }}"
                class="w-full rounded-xl border border-[var(--color-hairline)] bg-[var(--color-surface)] py-3 pl-10 pr-4 text-sm placeholder:text-zinc-400 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20 dark:bg-[var(--color-surface-2)] dark:text-zinc-100">
            <div wire:loading wire:target="runSmart" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-zinc-400">{{ __('Thinking…') }}</div>
        </div>
        @if ($smartNote)
            <p class="mt-2 text-xs font-medium text-violet-700 dark:text-violet-200">{{ $smartNote }}</p>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="mt-5 flex gap-6 border-b border-[var(--color-hairline)] text-sm">
        @foreach (['customers' => __('Customers Search'), 'leads' => __('Leads Search')] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                @class([
                    '-mb-px border-b-2 py-2.5 font-medium transition',
                    'border-blue-600 text-blue-600' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
                ])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- Forms --}}
    <div class="{{ $card }} mt-5">
        @if ($tab === 'customers')
            <form wire:submit="search">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="{{ $lbl }}">{{ __('Name') }}</label>
                        <input type="text" wire:model="cf.name" placeholder="{{ __('Full name') }}" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Min. 3 characters') }}</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('Email') }}</label>
                        <input type="text" wire:model="cf.email" placeholder="email@example.com" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Must include @') }}</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('Phone') }}</label>
                        <input type="text" wire:model="cf.phone" placeholder="(555) 000-0000" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Any format — dashes/spaces OK') }}</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('City') }}</label>
                        <input type="text" wire:model="cf.city" placeholder="{{ __('City') }}" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Min. 3 characters') }}</p>
                    </div>
                </div>
                <div class="mt-4 max-w-xs">
                    <label class="{{ $lbl }}">{{ __('Policy #') }}</label>
                    <input type="text" wire:model="cf.policy" placeholder="AHC-2026-" class="{{ $inp }}">
                    <p class="{{ $hint }}">{{ __('Partial match OK') }}</p>
                </div>

                @if ($smartStatus || $smartPlan || $smartSalesman || $smartState)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach (array_filter(['status' => $smartStatus, 'plan' => $smartPlan, 'salesman' => $smartSalesman, 'state' => $smartState]) as $k => $v)
                            <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ $k }}: {{ $v }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 flex gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <flux:icon name="magnifying-glass" variant="micro" class="size-4" /> {{ __('Search') }}
                    </button>
                    <button type="button" wire:click="clear" class="rounded-lg border border-[var(--color-hairline)] px-4 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">{{ __('Clear') }}</button>
                </div>
            </form>
        @else
            <form wire:submit="search">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="{{ $lbl }}">{{ __('Name') }}</label>
                        <input type="text" wire:model="lf.name" placeholder="{{ __('Full name') }}" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Min. 3 characters') }}</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('Email') }}</label>
                        <input type="text" wire:model="lf.email" placeholder="email@example.com" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Must include @') }}</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('Phone') }}</label>
                        <input type="text" wire:model="lf.phone" placeholder="(555) 000-0000" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Any format — dashes/spaces OK') }}</p>
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('City') }}</label>
                        <input type="text" wire:model="lf.city" placeholder="{{ __('City') }}" class="{{ $inp }}">
                        <p class="{{ $hint }}">{{ __('Min. 3 characters') }}</p>
                    </div>
                </div>
                <div class="mt-4 max-w-xs">
                    <label class="{{ $lbl }}">{{ __('Campaign') }}</label>
                    <select wire:model="lf.campaign" class="{{ $inp }}">
                        <option value="">{{ __('Any campaign') }}</option>
                        @foreach ($campaigns as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="{{ $hint }}">{{ __('Optional') }}</p>
                </div>

                <div class="mt-5 flex gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <flux:icon name="magnifying-glass" variant="micro" class="size-4" /> {{ __('Search') }}
                    </button>
                    <button type="button" wire:click="clear" class="rounded-lg border border-[var(--color-hairline)] px-4 py-2 text-sm font-medium text-zinc-600 hover:bg-black/5 dark:text-zinc-300 dark:hover:bg-white/5">{{ __('Clear') }}</button>
                </div>
            </form>
        @endif
    </div>

    {{-- Results --}}
    @if ($searched)
        @php($rows = $tab === 'leads' ? $leads : $customers)
        @php($applied = $tab === 'leads' ? $leadFilterCount : $customerFilterCount)

        <div class="mt-5 {{ $card }} !p-0 overflow-hidden">
            <div class="border-b border-[var(--color-hairline)] px-4 py-3">
                <h2 class="font-semibold text-[var(--color-navy-900)] dark:text-white">
                    @if ($applied === 0) {{ __('Add a filter to search') }}
                    @else {{ $rows->count() }} {{ $tab === 'leads' ? \Illuminate\Support\Str::plural('lead', $rows->count()) : \Illuminate\Support\Str::plural('customer', $rows->count()) }} {{ __('found') }}
                    @endif
                </h2>
            </div>

            @if ($applied === 0)
                <div class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('Fill in at least one filter above, or use Smart Search.') }}</div>
            @elseif ($tab === 'leads')
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="border-b border-[var(--color-hairline)] text-left">
                            <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                                <th>{{ __('Lead') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Email') }}</th><th>{{ __('Status') }}</th><th>{{ __('Campaign') }}</th><th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-hairline)]">
                            @forelse ($rows as $r)
                                <tr wire:key="lr-{{ $r->id }}" class="[&>td]:px-4 [&>td]:py-3">
                                    <td>
                                        <a href="{{ route('leads.show', $r->id) }}" wire:navigate class="font-semibold text-[var(--color-navy-900)] hover:text-blue-600 dark:text-zinc-100">{{ trim($r->first_name.' '.$r->last_name) ?: '—' }}</a>
                                        <div class="text-xs text-zinc-400">{{ optional($r->created_at)->format('m/d/Y h:i A') ?? '—' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        @if ($r->contact)<a href="tel:{{ $r->contact }}" class="text-blue-600 underline decoration-blue-600/30">{{ $r->contact }}</a>@else — @endif
                                    </td>
                                    <td class="text-zinc-600 dark:text-zinc-300">{{ $r->email ?: '—' }}</td>
                                    <td>
                                        @if ($r->leadStatus?->title)
                                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $r->leadStatus->title }}</span>
                                        @else <span class="text-zinc-400">—</span> @endif
                                    </td>
                                    <td class="text-zinc-500">{{ $r->campaign?->name ?: '—' }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <a href="{{ route('sale-sheet.index', ['lead' => $r->id]) }}" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                            <flux:icon name="user-plus" variant="micro" class="size-4" /> {{ __('Convert to Client') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No leads match. Try broadening the filters.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="border-b border-[var(--color-hairline)] text-left">
                            <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-zinc-400">
                                <th>{{ __('Customer') }}</th><th>{{ __('Email') }}</th><th>{{ __('Policy #') }}</th><th>{{ __('Package') }}</th><th>{{ __('Status') }}</th><th>{{ __('Salesman') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-hairline)]">
                            @forelse ($rows as $r)
                                @php($sv = (int) $r->status)
                                <tr wire:key="cr-{{ $r->id }}" onclick="Livewire.navigate('{{ route('customers.show', $r->id) }}')"
                                    class="cursor-pointer [&>td]:px-4 [&>td]:py-3 hover:bg-black/[0.02] dark:hover:bg-white/5">
                                    <td>
                                        <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ trim($r->first_name.' '.$r->last_name) ?: '—' }}</div>
                                        <div class="text-xs text-zinc-400">{{ trim(collect([$r->city, $r->state])->filter()->implode(', ')) ?: $r->contact }}</div>
                                    </td>
                                    <td class="text-zinc-600 dark:text-zinc-300">{{ $r->email ?: '—' }}</td>
                                    <td class="whitespace-nowrap font-medium text-blue-600 underline decoration-blue-600/30">{{ $r->policy_number ?: '—' }}</td>
                                    <td>
                                        @if ($r->propertyPackage?->title)
                                            <span class="font-medium text-[var(--color-navy-900)] dark:text-zinc-100">{{ $r->propertyPackage->title }}</span>
                                        @else <span class="text-zinc-400">—</span> @endif
                                    </td>
                                    <td>
                                        <span @class([
                                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $sv === 1,
                                            'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' => $sv === 0,
                                            'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $sv === 5,
                                        ])>{{ $sv === 1 ? __('Active') : ($sv === 5 ? __('Bypass') : __('Suspended')) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap text-zinc-600 dark:text-zinc-300">{{ $r->salesman_name ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No customers match. Try broadening the filters.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <p class="mt-6 flex items-center justify-center gap-1.5 text-center text-xs text-zinc-400">
        <flux:icon name="shield-check" variant="micro" class="size-3.5" />
        {{ __('All search activity is monitored and logged. Unusual patterns are automatically reported to administrators.') }}
    </p>
</div>
