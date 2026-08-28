<div class="w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Control Center') }}</flux:heading>
    <flux:text class="mt-1">{{ __('Every runtime switch — billing, outbound communications and integrations — in one place.') }}</flux:text>

    <div class="mt-6 space-y-3">
        <div @class([
            'rounded-xl border p-4 text-sm font-semibold',
            'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300' => $billingLive,
            'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300' => ! $billingLive,
        ])>
            {{ $billingLive
                ? __('BILLING LIVE — real charges, subscriptions and customer profiles are being created at Authorize.Net.')
                : __('BILLING DRY-RUN — gateway calls are simulated; nothing reaches Authorize.Net.') }}
        </div>
        <div @class([
            'rounded-xl border p-4 text-sm font-semibold',
            'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300' => $commsOn,
            'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700 dark:bg-white/5 dark:text-zinc-300' => ! $commsOn,
        ])>
            {{ $commsOn
                ? __('COMMUNICATIONS ON — email / SMS / voice may reach real customers per the switches below.')
                : __('COMMUNICATIONS OFF — nothing is sent. Email is written to the log only.') }}
        </div>
    </div>

    <form wire:submit="save" class="mt-6 space-y-8">
        @foreach ($groups as $group => $groupLabel)
            <section class="rounded-xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-6">
                <h2 class="text-base font-bold text-[var(--color-navy-900)] dark:text-white">{{ $groupLabel }}</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($rows[$group] ?? [] as $key)
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model.live="s.{{ $key }}"
                                class="mt-0.5 rounded border-zinc-300 text-blue-600 focus:ring-blue-600">
                            <span>
                                <span class="block font-semibold text-[var(--color-navy-900)] dark:text-zinc-100">{{ $labels[$key][0] ?? $key }}</span>
                                <span class="block text-sm text-zinc-500">{{ $labels[$key][1] ?? '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach

        <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
    </form>
</div>
