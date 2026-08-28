@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[10px] font-semibold uppercase tracking-wide text-zinc-400';
    $money = fn ($n) => '$'.number_format((float) $n, 2);
@endphp

<div class="w-full">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">
                <flux:icon name="shield-exclamation" variant="mini" class="size-6 text-rose-500" /> {{ __('Disputes') }}
            </h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Chargeback and dispute management via Zen Payments') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="refresh">{{ __('Refresh') }}</flux:button>
            <flux:button size="sm" variant="primary" icon="arrows-right-left" wire:click="syncFromZen">{{ __('Sync from Zen') }}</flux:button>
        </div>
    </div>

    @unless ($zenConnected)
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10"
             x-data="{ copied: false }">
            <div class="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-300">
                <flux:icon name="exclamation-triangle" variant="micro" class="size-4" />
                {{ __('Add this webhook URL to both Zen Payments accounts') }}
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="rounded-md border border-amber-300 bg-white px-2 py-1 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-black/20 dark:text-amber-200">{{ $webhookUrl }}</code>
                <button type="button" class="text-xs font-semibold text-amber-700 underline dark:text-amber-300"
                        x-on:click="navigator.clipboard.writeText('{{ $webhookUrl }}'); copied = true; setTimeout(() => copied = false, 1500)">
                    <span x-show="!copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </button>
            </div>
            <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                {{ __('Also set ZEN_API_KEY_1 and ZEN_API_KEY_2 in the environment secrets to enable manual sync.') }}
            </p>
        </div>
    @endunless

    {{-- KPIs --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="{{ $card }}"><div class="flex items-center gap-2"><flux:icon name="shield-exclamation" variant="micro" class="size-4 text-rose-500" /><span class="{{ $lbl }}">{{ __('Open Disputes') }}</span></div><div class="mt-1 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($totals['open']) }}</div></div>
        <div class="{{ $card }}"><div class="flex items-center gap-2"><flux:icon name="currency-dollar" variant="micro" class="size-4 text-rose-500" /><span class="{{ $lbl }}">{{ __('At Risk') }}</span></div><div class="mt-1 text-3xl font-bold text-rose-600 dark:text-rose-400">{{ $money($totals['at_risk']) }}</div></div>
        <div class="{{ $card }}"><div class="flex items-center gap-2"><flux:icon name="document-text" variant="micro" class="size-4 text-blue-500" /><span class="{{ $lbl }}">{{ __('Zen Records') }}</span></div><div class="mt-1 text-3xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ number_format($totals['zen_records']) }}</div></div>
    </div>

    {{-- Tabs --}}
    <div class="mt-5 inline-flex rounded-xl border border-[var(--color-hairline)] p-1 text-sm font-semibold">
        <button type="button" wire:click="$set('tab', 'zen')" @class(['rounded-lg px-4 py-2', 'bg-blue-600 text-white' => $tab === 'zen', 'text-zinc-500' => $tab !== 'zen'])>{{ __('Zen Records') }} <span class="ml-1 text-xs">{{ $totals['zen_records'] }}</span></button>
        <button type="button" wire:click="$set('tab', 'customers')" @class(['rounded-lg px-4 py-2', 'bg-blue-600 text-white' => $tab === 'customers', 'text-zinc-500' => $tab !== 'customers'])>{{ __('Disputed Customers') }} <span class="ml-1 text-xs">{{ $totals['open'] }}</span></button>
    </div>

    @if ($tab === 'zen')
        <div class="{{ $card }} mt-4 py-16 text-center">
            <flux:icon name="arrows-right-left" variant="outline" class="mx-auto size-8 text-zinc-300" />
            <p class="mt-3 font-semibold text-[var(--color-navy-900)] dark:text-white">{{ __('Zen Payments is not connected') }}</p>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Add the webhook above to both Zen accounts and set the API keys, then use "Sync from Zen".') }}</p>
        </div>
    @else
        <div class="{{ $card }} mt-4">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <div class="flex gap-1.5">
                    @foreach (['open' => __('Open'), 'won' => __('Won'), 'lost' => __('Lost'), 'all' => __('All')] as $key => $label)
                        <button type="button" wire:click="$set('state', '{{ $key }}')" wire:key="s-{{ $key }}"
                            @class(['rounded-lg px-3 py-1.5 text-sm font-semibold', 'bg-blue-600 text-white' => $state === $key, 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $state !== $key])>{{ $label }}</button>
                    @endforeach
                </div>
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Customer name, email, policy #')" class="max-w-sm" clearable />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr class="border-b border-[var(--color-hairline)] text-left {{ $lbl }}">
                            <th class="py-2 pr-3">{{ __('Customer') }}</th>
                            <th class="px-3">{{ __('Amount') }}</th>
                            <th class="px-3">{{ __('Transaction') }}</th>
                            <th class="px-3">{{ __('Outcome') }}</th>
                            <th class="px-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-hairline)]">
                        @forelse ($rows as $p)
                            <tr wire:key="d-{{ $p->id }}">
                                <td class="py-3 pr-3">
                                    @if ($p->userInfo)
                                        <a class="font-semibold text-blue-600 hover:underline" wire:navigate href="{{ route('customers.show', $p->userInfo) }}">
                                            {{ trim($p->userInfo->first_name.' '.$p->userInfo->last_name) ?: '—' }}
                                        </a>
                                        <div class="text-xs text-zinc-400">#{{ $p->userInfo->policy_number ?: '—' }}</div>
                                    @else
                                        <span class="text-zinc-400">{{ __('Client #').$p->client_id }}</span>
                                    @endif
                                </td>
                                <td class="px-3 font-semibold">{{ $money($p->amount) }}</td>
                                <td class="px-3 font-mono text-xs text-zinc-500">{{ $p->transaction_id ?: '—' }}</td>
                                <td class="px-3">
                                    @if ($p->win)<span class="rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">{{ __('won') }}</span>
                                    @elseif ($p->loss)<span class="rounded-md bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">{{ __('lost') }}</span>
                                    @else<span class="rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ __('open') }}</span>@endif
                                </td>
                                <td class="px-3">
                                    <div class="flex justify-end gap-1">
                                        @if (! $p->win && ! $p->loss)
                                            <flux:button size="xs" variant="ghost" wire:click="resolve({{ $p->id }}, 'win')">{{ __('Won') }}</flux:button>
                                            <flux:button size="xs" variant="ghost" wire:click="resolve({{ $p->id }}, 'loss')">{{ __('Lost') }}</flux:button>
                                        @endif
                                        <flux:button size="xs" variant="ghost" wire:click="reset_({{ $p->id }})" wire:confirm="{{ __('Clear the dispute flag?') }}">{{ __('Clear') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-zinc-400">{{ __('No disputes match.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </div>
    @endif
</div>
