@php
    $card = 'rounded-2xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-5';
    $lbl = 'text-[11px] font-semibold uppercase tracking-wide text-zinc-400';
    $inp = 'mt-1 w-full rounded-lg border border-[var(--color-hairline)] bg-[var(--color-surface)] px-3 py-2 text-sm focus:border-blue-600 focus:outline-none dark:bg-[var(--color-surface-2)] dark:text-zinc-100';
@endphp

<div class="w-full max-w-4xl" x-data>
    <div class="flex items-start gap-3">
        <a href="{{ route('commissions.index') }}" wire:navigate class="mt-1 text-zinc-400 hover:text-blue-600"><flux:icon name="chevron-left" variant="micro" class="size-5" /></a>
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Commission Settings') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('Configure Spliffy bonuses, after-hours rules, and view plan commission rates. All plan types use fixed commission formulas.') }}</p>
        </div>
    </div>

    {{-- Base salesman rates --}}
    <div class="{{ $card }} mt-5">
        <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Base Salesman Rates') }}</h2>
        <p class="mt-1 text-sm text-zinc-500">{{ __('These rates are') }} <span class="font-semibold text-amber-500">{{ __('not used') }}</span> {{ __('in commission calculations — all plan types use fixed formulas. Rates are retained for reference only.') }}</p>

        <div class="mt-4 flex items-end justify-between gap-4 border-b border-[var(--color-hairline)] pb-4">
            <div>
                <div class="font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Global Rate') }}</div>
                <div class="text-xs text-zinc-500">{{ __('Default applied to all salesmen unless overridden below.') }}</div>
            </div>
            <div class="flex items-center gap-1">
                <input type="number" step="0.1" wire:model="cfg.base_rate" class="{{ $inp }} !mt-0 w-24 text-right">
                <span class="text-sm text-zinc-400">%</span>
            </div>
        </div>

        <div class="{{ $lbl }} mt-4">{{ __('Individual Overrides') }}</div>
        <div class="mt-2 divide-y divide-[var(--color-hairline)]">
            @foreach ($salesmen as $s)
                <div wire:key="ov-{{ $s->id }}" class="flex items-center justify-between gap-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <span class="grid size-7 place-items-center rounded-lg bg-blue-600 text-[10px] font-bold text-white">{{ $s->initials() }}</span>
                        <div>
                            <div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $s->name }}</div>
                            <div class="text-[11px] {{ $s->status ? 'text-emerald-600' : 'text-zinc-400' }}">{{ $s->status ? __('Active') : __('Inactive') }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <input type="number" step="0.1" wire:model="cfg.overrides.{{ $s->id }}" placeholder="{{ $cfg['base_rate'] }}" class="{{ $inp }} !mt-0 w-24 text-right">
                        <span class="text-sm text-zinc-400">%</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Plan type rates --}}
    <div class="{{ $card }} mt-5">
        <h2 class="font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Plan Type Commission Rates') }}</h2>
        <p class="mt-1 text-sm text-zinc-500">{{ __('All commission amounts are calculated using fixed percentages — no salesman rate involved. Each plan category has its own formula.') }}</p>

        <div class="mt-4 space-y-3">
            @foreach ([
                ['Monthly', 'monthly_pct', 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300', __('Commission = monthly price × :pct% (price − 10%). Fixed rate — salesman base rate does not apply.'), 'e.g. $49.49/mo × '],
                ['Yearly — Full Upfront', 'yearly_full_pct', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', __('Commission = plan total × :pct%. Fixed rate — salesman base rate does not apply. (1-yr, 2-yr, VIP 3-yr, VIP 6-yr)'), 'e.g. $4,200 total × '],
                ['Yearly — Installments', 'yearly_inst_pct', 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', __('Commission = plan total × :pct%. Fixed rate — salesman base rate does not apply. (yearly, installment)'), 'e.g. $465 plan total × '],
            ] as [$name, $key, $badge, $desc, $example])
                <div class="rounded-xl border border-[var(--color-hairline)] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $badge }}">{{ $name }}</span>
                            <p class="mt-2 text-sm text-zinc-500">{{ str_replace(':pct', rtrim(rtrim(number_format($cfg[$key], 2), '0'), '.'), $desc) }}</p>
                            <p class="mt-0.5 font-mono text-xs text-zinc-400">{{ $example }}{{ rtrim(rtrim(number_format($cfg[$key], 2), '0'), '.') }}%</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <input type="number" step="0.1" wire:model="cfg.{{ $key }}" class="{{ $inp }} !mt-0 w-24 text-right font-bold">
                            <span class="text-sm text-zinc-400">%</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Spliffy bonuses --}}
    <div class="{{ $card }} mt-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="sparkles" variant="micro" class="size-4 text-violet-500" /> {{ __('Spliffy Bonuses') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Flat cash bonuses triggered by card count (full sales, not properties). A sale with 10 properties on one card = 1 card.') }}</p>
            </div>
            <label class="inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model="cfg.spiff_enabled" class="peer sr-only">
                <span class="relative h-6 w-11 rounded-full bg-zinc-300 transition peer-checked:bg-blue-600 dark:bg-white/15 after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
            </label>
        </div>

        <div class="mt-4 space-y-2">
            @foreach ($cfg['spiff_tiers'] as $i => $tier)
                <div wire:key="tier-{{ $i }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-[var(--color-hairline)] p-3">
                    <span class="grid size-8 place-items-center rounded-lg bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">T{{ $i + 1 }}</span>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Tier :n', ['n' => $i + 1]) }}</div>
                        <div class="text-xs text-zinc-500">{{ __('Cards needed to earn this bonus') }}</div>
                    </div>
                    <input type="number" min="1" wire:model="cfg.spiff_tiers.{{ $i }}.cards" class="{{ $inp }} !mt-0 w-20 text-center">
                    <span class="text-xs text-zinc-400">{{ __('cards') }}</span>
                    <span class="text-sm text-zinc-400">$</span>
                    <input type="number" min="0" wire:model="cfg.spiff_tiers.{{ $i }}.amount" class="{{ $inp }} !mt-0 w-28 text-center">
                    <button type="button" wire:click="removeTier({{ $i }})" class="text-zinc-400 hover:text-red-600"><flux:icon name="x-mark" variant="micro" class="size-5" /></button>
                </div>
            @endforeach
            <button type="button" wire:click="addTier" class="inline-flex items-center gap-1 text-sm font-semibold text-violet-600 hover:underline"><flux:icon name="plus" variant="micro" class="size-4" /> {{ __('Add Tier') }}</button>
        </div>

        <div class="mt-3 rounded-xl border border-violet-200 bg-violet-50 p-3 text-xs text-violet-700 dark:border-violet-500/25 dark:bg-violet-500/10 dark:text-violet-200">
            <span class="font-bold">{{ __('How it works:') }}</span> {{ __('Each tier bonus stacks. Bonuses reset each pay period. 4 properties on one card = 1 card.') }}
        </div>
    </div>

    {{-- Billing Spliffy --}}
    <div class="{{ $card }} mt-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="trophy" variant="micro" class="size-4 text-amber-500" /> {{ __('Billing Spliffy') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Weekly top-biller bonus. The salesman who charges the most this week earns this at the end of each week.') }}</p>
            </div>
            <label class="inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model="cfg.billing_enabled" class="peer sr-only">
                <span class="relative h-6 w-11 rounded-full bg-zinc-300 transition peer-checked:bg-blue-600 dark:bg-white/15 after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
            </label>
        </div>
        <div class="mt-4 flex items-center justify-between gap-4 rounded-xl border border-[var(--color-hairline)] p-3">
            <div>
                <div class="text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ __('Weekly Bonus Amount') }}</div>
                <div class="text-xs text-zinc-500">{{ __('Awarded to the top biller at end of each week') }}</div>
            </div>
            <div class="flex items-center gap-1"><span class="text-sm text-zinc-400">$</span><input type="number" min="0" wire:model="cfg.billing_amount" class="{{ $inp }} !mt-0 w-28 text-center"></div>
        </div>
    </div>

    {{-- After-hours multiplier --}}
    <div class="{{ $card }} mt-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 font-bold text-[var(--color-navy-900)] dark:text-white"><flux:icon name="moon" variant="micro" class="size-4 text-violet-500" /> {{ __('After-Hours Multiplier') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Sales made after the cutoff time earn a multiplier on their base commission. Encourages evening selling.') }}</p>
            </div>
            <label class="inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model="cfg.ah_enabled" class="peer sr-only">
                <span class="relative h-6 w-11 rounded-full bg-zinc-300 transition peer-checked:bg-blue-600 dark:bg-white/15 after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
            </label>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="{{ $lbl }}">{{ __('Cutoff Time (24-hr)') }}</label>
                <div class="flex items-center gap-2">
                    <input type="number" min="0" max="23" wire:model.live="cfg.ah_cutoff" class="{{ $inp }} w-24 text-center">
                    <span class="text-sm text-zinc-400">= {{ \Illuminate\Support\Carbon::createFromTime((int) $cfg['ah_cutoff'])->format('g:i A') }}</span>
                </div>
            </div>
            <div>
                <label class="{{ $lbl }}">{{ __('Commission Multiplier') }}</label>
                <div class="flex items-center gap-2">
                    <input type="number" step="0.1" min="1" wire:model="cfg.ah_multiplier" class="{{ $inp }} w-24 text-center">
                    <span class="text-sm text-zinc-400">×</span>
                </div>
            </div>
        </div>
        <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-700 dark:border-blue-500/25 dark:bg-blue-500/10 dark:text-blue-200">
            <span class="font-bold">{{ __('Example:') }}</span>
            {{ __('A sale at 8 PM earns :m× the normal commission. On a $49.49/mo plan: base = $44.54 → after-hours = $:total.', [
                'm' => $cfg['ah_multiplier'],
                'total' => number_format(44.54 * (float) $cfg['ah_multiplier'], 2),
            ]) }}
        </div>
    </div>

    <div class="sticky bottom-4 mt-6 flex justify-end">
        <button type="button" wire:click="save" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-blue-700">
            <flux:icon name="check" variant="micro" class="size-4" /> {{ __('Save Settings') }}
        </button>
    </div>
</div>
