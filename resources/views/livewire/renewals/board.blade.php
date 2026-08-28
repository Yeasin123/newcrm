@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[10px] font-semibold uppercase tracking-wide text-zinc-400';
    $money = fn ($n) => '$'.number_format((float) $n, 2);
    $badgeTone = fn ($t) => match (true) {
        str_contains($t, 'Renewed'), str_contains($t, 'Upgraded') => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        str_contains($t, 'No Answer') => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        str_contains($t, 'Not Interested') => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        str_contains($t, 'Skipped') => 'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300',
        default => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    };
    $daysLeft = $row?->days_left ?? 0;
    $cardOnFile = $customer?->cardProfiles->first();
@endphp

<div class="w-full">
    {{-- Full-bleed navy header --}}
    <div class="-m-4 mb-5 bg-[var(--color-navy-900)] text-white sm:-m-6 sm:mb-6 lg:-m-8 lg:mb-8">
        <div class="flex flex-wrap items-center gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('renewals.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-white/70 hover:text-white">
                <flux:icon name="arrow-left" variant="micro" class="size-4" /> {{ __('Queue') }}
            </a>
            <span class="text-lg font-bold">{{ __('Renewal Board') }}</span>
            @if ($customer)
                <span @class([
                    'rounded-full px-2.5 py-0.5 text-xs font-bold',
                    'bg-rose-500 text-white' => $daysLeft <= 7,
                    'bg-amber-500 text-white' => $daysLeft > 7,
                ])>{{ max(0, $daysLeft) }}D {{ __('TO RENEWAL') }}</span>
            @endif

            <div class="inline-flex rounded-lg bg-white/10 p-0.5 text-xs font-semibold">
                @foreach (['yearly' => __('Yearly'), 'monthly' => __('Monthly'), 'all' => __('All')] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')"
                        @class(['rounded-md px-3 py-1 transition', 'bg-white text-[var(--color-navy-900)]' => $filter === $key, 'text-white/70 hover:text-white' => $filter !== $key])>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <span class="text-sm text-white/60">{{ number_format($total) }} {{ __('in queue') }}</span>

            <div class="ml-auto flex items-center gap-3 text-sm">
                <span class="tabular-nums text-white/70">{{ $position }} / {{ number_format($total) }}</span>
                <button type="button" wire:click="prev" @disabled($position <= 1) class="rounded-md p-1.5 text-white/70 hover:bg-white/10 disabled:opacity-30">
                    <flux:icon name="chevron-left" variant="micro" class="size-4" />
                </button>
                <button type="button" wire:click="next" @disabled($position >= $total) class="rounded-md p-1.5 text-white/70 hover:bg-white/10 disabled:opacity-30">
                    <flux:icon name="chevron-right" variant="micro" class="size-4" />
                </button>
            </div>
        </div>
    </div>

    @if (! $customer)
        <div class="{{ $card }} text-center text-sm text-zinc-400">{{ __('Renewal queue is empty for this filter.') }}</div>
    @else
        <div class="mb-4 flex flex-wrap items-baseline gap-3">
            <span class="text-sm text-zinc-500">{{ __('Annual Value:') }}</span>
            <span class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ $money($row->annual) }}</span>
            <span class="text-sm font-semibold {{ $daysLeft <= 7 ? 'text-rose-500' : 'text-amber-500' }}">{{ max(0, $daysLeft) }}d {{ __('remaining') }}</span>
        </div>

        <div class="grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)_16rem]">
            {{-- ============ LEFT ============ --}}
            <div class="space-y-5">
                <div class="{{ $card }} space-y-3">
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-blue-600/10 text-sm font-bold text-blue-700 dark:text-blue-300">
                            {{ \Illuminate\Support\Str::of($row->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                        </span>
                        <div>
                            <div class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ $row->name }}</div>
                            <a href="{{ route('customers.show', $customer->id) }}" wire:navigate class="inline-flex items-center gap-1 text-xs text-blue-600">
                                {{ __('View profile') }} <flux:icon name="arrow-top-right-on-square" variant="micro" class="size-3" />
                            </a>
                        </div>
                    </div>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex items-center gap-2 text-zinc-500"><flux:icon name="clock" variant="micro" class="size-4" /> {{ now('America/New_York')->format('g:i A T') }}</div>
                        <div class="flex items-center gap-2"><flux:icon name="phone" variant="micro" class="size-4 text-zinc-400" /> <a href="tel:{{ $row->contact }}" class="text-blue-600">{{ $row->contact ?: '—' }}</a></div>
                        <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><flux:icon name="envelope" variant="micro" class="size-4 text-zinc-400" /> {{ $row->email ?: '—' }}</div>
                        <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><flux:icon name="map-pin" variant="micro" class="size-4 text-zinc-400" /> {{ $row->address ?: '—' }}</div>
                    </dl>
                    <div class="border-t border-[var(--color-hairline)] pt-3">
                        <dl class="space-y-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Policy') }}</dt><dd class="font-semibold">{{ $row->policy_number }}</dd></div>
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Customer Since') }}</dt><dd class="font-semibold">{{ $row->started ? \Illuminate\Support\Carbon::parse($row->started)->format('M j, Y') : '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Expires') }}</dt><dd class="font-semibold text-rose-500">{{ $row->expires->format('M j, Y') }}</dd></div>
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Salesman') }}</dt><dd class="font-semibold">{{ $row->salesman_name ?: '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Service Fee') }}</dt><dd class="font-semibold">{{ $money($row->call_fee) }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="{{ $card }}">
                    <div class="{{ $lbl }} mb-2">{{ __('Payment History') }}</div>
                    <ul class="space-y-1.5 text-sm">
                        @forelse ($payments as $p)
                            <li class="flex items-center justify-between" wire:key="pay-{{ $p->id }}">
                                <span class="text-zinc-500">{{ optional($p->payment_date ? \Illuminate\Support\Carbon::parse($p->payment_date) : $p->created_at)?->format('M j, Y') }}</span>
                                <span class="flex items-center gap-2 font-semibold">{{ $money($p->amount) }}
                                    <span class="size-2 rounded-full {{ (int) $p->payment_status === 1 ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                </span>
                            </li>
                        @empty
                            <li class="text-zinc-400">{{ __('No payments on file.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- ============ CENTER ============ --}}
            <div class="space-y-5">
                <div class="{{ $card }}">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 place-items-center rounded-lg bg-blue-600 text-white"><flux:icon name="shield-check" variant="micro" class="size-5" /></span>
                            <div>
                                <span class="rounded-md bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">{{ $row->package }}</span>
                                <div class="text-sm text-zinc-500">{{ $row->term_label }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ $money($row->annual) }}</div>
                            <div class="text-xs text-zinc-400">{{ $row->is_yearly ? __('/year') : __('/month') }}</div>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-[var(--color-hairline)] p-3"><div class="{{ $lbl }}">{{ __('Start Date') }}</div><div class="mt-0.5 font-semibold">{{ $row->started ? \Illuminate\Support\Carbon::parse($row->started)->format('M j, Y') : '—' }}</div></div>
                        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-500/30 dark:bg-rose-500/10"><div class="{{ $lbl }}">{{ __('Expiry Date') }}</div><div class="mt-0.5 font-semibold text-rose-600 dark:text-rose-400">{{ $row->expires->format('M j, Y') }}</div></div>
                        <div class="rounded-xl border border-[var(--color-hairline)] p-3"><div class="{{ $lbl }}">{{ __('Annual Value') }}</div><div class="mt-0.5 font-semibold">{{ $money($row->annual) }}</div></div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-500/30 dark:bg-emerald-500/10"><div class="{{ $lbl }}">{{ __('New Expiry (if Renewed)') }}</div><div class="mt-0.5 font-semibold text-emerald-600 dark:text-emerald-400">{{ $row->renewed_expiry->format('M j, Y') }}</div></div>
                    </div>
                    <p class="mt-3 text-sm {{ $row->addons ? 'text-zinc-500' : 'text-amber-600' }}">
                        {{ $row->addons ? trans_choice(':count add-on|:count add-ons', count($row->addons), ['count' => count($row->addons)]) : __('No add-ons — upsell opportunity!') }}
                    </p>
                </div>

                <div class="{{ $card }}">
                    <div class="{{ $lbl }} mb-2">{{ __('Notes') }}</div>
                    <flux:textarea wire:model.live="noteBody" rows="3" :placeholder="__('Add a note for this renewal attempt…')" />
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-xs text-zinc-400">{{ mb_strlen($noteBody) }} {{ __('chars') }}</span>
                        <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="postNote">{{ __('Post Note') }}</flux:button>
                    </div>
                    @error('noteBody') <flux:text class="mt-1 text-sm text-rose-500">{{ $message }}</flux:text> @enderror
                </div>

                <div class="{{ $card }}">
                    <div class="{{ $lbl }} mb-2">{{ __('Contact History') }}</div>
                    <ul class="divide-y divide-[var(--color-hairline)] text-sm">
                        @forelse ($history as $h)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2.5" wire:key="hist-{{ $h->id }}">
                                <span class="flex items-center gap-2">
                                    <span class="rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $badgeTone($h->button_text) }}">{{ $h->button_text }}</span>
                                    <span class="text-zinc-500">{{ $h->user?->name ?? '—' }}</span>
                                </span>
                                <span class="text-xs text-zinc-400">{{ optional($h->created_at ?? ($h->create_date ? \Illuminate\Support\Carbon::parse($h->create_date) : null))?->format('M j, g:i A') }}</span>
                            </li>
                        @empty
                            <li class="py-3 text-zinc-400">{{ __('No contact attempts yet.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- ============ RIGHT: ACTIONS ============ --}}
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-xs text-zinc-500">
                    <flux:icon name="credit-card" variant="micro" class="size-4" />
                    @if ($cardOnFile)
                        {{ __('Card ending') }} {{ $cardOnFile->last4 ?: '••••' }} · {{ __('exp') }} {{ $cardOnFile->expMonth ? str_pad($cardOnFile->expMonth, 2, '0', STR_PAD_LEFT).'/'.substr((string) $cardOnFile->expYear, -2) : '—' }}
                    @else
                        {{ __('No card on file') }}
                    @endif
                </div>
                <div class="{{ $lbl }}">{{ __('Actions') }}</div>

                @php
                    $actions = [
                        ['renewed', 'Renewed!', 'Customer agreed to renew', 'bg-emerald-600 hover:bg-emerald-700', 'check-circle'],
                        ['upgraded', 'Upgraded Plan', 'Moved to a higher tier', 'bg-violet-600 hover:bg-violet-700', 'arrow-trending-up'],
                        ['spoke_with', 'Spoke With', 'Had a conversation', 'bg-blue-600 hover:bg-blue-700', 'chat-bubble-left-right'],
                        ['call_back', 'Schedule Call Back', 'They requested a call back', 'bg-sky-600 hover:bg-sky-700', 'phone-arrow-up-right'],
                        ['no_answer', 'No Answer', 'No one picked up', 'bg-amber-500 hover:bg-amber-600', 'phone-x-mark'],
                        ['not_interested', 'Not Interested', 'Declined to renew', 'bg-rose-600 hover:bg-rose-700', 'x-circle'],
                    ];
                @endphp
                @foreach ($actions as [$key, $label, $desc, $tone, $icon])
                    <button type="button" wire:click="disposition('{{ $key }}')" wire:loading.attr="disabled"
                        class="flex w-full items-start gap-2 rounded-xl px-3.5 py-2.5 text-left text-white transition {{ $tone }}">
                        <flux:icon name="{{ $icon }}" variant="micro" class="mt-0.5 size-4 shrink-0" />
                        <span><span class="block text-sm font-bold">{{ __($label) }}</span><span class="block text-[11px] text-white/80">{{ __($desc) }}</span></span>
                    </button>
                @endforeach

                <button type="button" wire:click="skip" class="flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--color-hairline)] px-3.5 py-2 text-sm font-semibold text-zinc-500 hover:bg-black/5 dark:hover:bg-white/5">
                    <flux:icon name="forward" variant="micro" class="size-4" /> {{ __('Skip') }}
                </button>

                <a href="{{ route('sale-sheet.index', ['renew' => $customer->id]) }}" target="_blank" rel="noopener"
                    class="flex w-full flex-col items-center gap-1 rounded-xl bg-blue-600 px-3.5 py-3 text-center text-white transition hover:bg-blue-700">
                    <span class="flex items-center gap-2 text-sm font-bold"><flux:icon name="shopping-cart" variant="micro" class="size-4" /> {{ __('Renew Customer — Sale Sheet') }}</span>
                </a>
                <p class="text-center text-[11px] text-zinc-400">{{ __('Opens the Sale Sheet with the customer’s plan pre-filled') }}</p>
            </div>
        </div>
    @endif
</div>
