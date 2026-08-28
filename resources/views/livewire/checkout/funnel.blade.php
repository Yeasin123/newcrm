<div class="mx-auto max-w-3xl px-4 py-12">
    {{-- Progress rail --}}
    <ol class="mb-10 flex items-center justify-between text-xs font-medium text-zinc-400">
        @foreach (['Zip', 'Your details', 'Plan', 'Add-ons', 'Review'] as $i => $label)
            @php($n = $i + 1)
            <li class="flex flex-1 items-center gap-2 {{ $n < 5 ? 'after:mx-2 after:h-px after:flex-1 after:bg-zinc-200 dark:after:bg-zinc-700' : '' }}">
                <span @class([
                    'flex h-6 w-6 items-center justify-center rounded-full border',
                    'border-blue-600 bg-blue-600 text-white' => $step >= $n,
                    'border-zinc-300 dark:border-zinc-600' => $step < $n,
                ])>{{ $n }}</span>
                <span @class(['text-zinc-800 dark:text-zinc-200' => $step >= $n])>{{ __($label) }}</span>
            </li>
        @endforeach
    </ol>

    {{-- Step 1 — zip --}}
    @if ($step === 1)
        <form wire:submit="submitZip" class="space-y-4">
            <h1 class="text-2xl font-bold">{{ __('Where is your home?') }}</h1>
            <p class="text-sm text-zinc-500">{{ __('Enter your ZIP code to see plans available in your area.') }}</p>
            <input type="text" wire:model="zip" inputmode="numeric" maxlength="5" placeholder="90210"
                   class="w-full rounded-lg border border-zinc-300 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            @error('zip') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button class="rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
        </form>
    @endif

    {{-- Step 2 — contact --}}
    @if ($step === 2)
        <form wire:submit="submitContact" class="space-y-4">
            <h1 class="text-2xl font-bold">{{ __('Tell us who you are') }}</h1>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium">{{ __('First name') }}</label>
                    <input type="text" wire:model="firstName" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error('firstName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Last name') }}</label>
                    <input type="text" wire:model="lastName" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error('lastName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Email') }}</label>
                    <input type="email" wire:model="email" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Phone') }}</label>
                    <input type="tel" wire:model="contact" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error('contact') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex gap-3">
                <button type="button" wire:click="$set('step', 1)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
                <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
            </div>
        </form>
    @endif

    {{-- Step 3 — plan --}}
    @if ($step === 3)
        <h1 class="text-2xl font-bold">{{ __('Choose a plan') }}</h1>
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach ($this->packages as $package)
                <button wire:click="selectPlan({{ $package->id }})" @class([
                    'rounded-xl border p-5 text-left transition hover:border-blue-500',
                    'border-blue-600 ring-2 ring-blue-600' => $packageId === $package->id,
                    'border-zinc-200 dark:border-zinc-800' => $packageId !== $package->id,
                ])>
                    <div class="font-semibold">{{ $package->title }}</div>
                    <div class="mt-2 text-sm text-zinc-500">${{ number_format((float) $package->monthly, 2) }}/mo</div>
                </button>
            @endforeach
        </div>
        <button type="button" wire:click="$set('step', 2)" class="mt-6 rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
    @endif

    {{-- Step 4 — add-ons --}}
    @if ($step === 4)
        <h1 class="text-2xl font-bold">{{ __('Add optional coverage') }}</h1>
        <div class="mt-6 space-y-3">
            @foreach ($this->addons as $addon)
                <label @class([
                    'flex cursor-pointer items-center justify-between rounded-lg border p-4',
                    'border-blue-600 bg-blue-50 dark:bg-blue-500/10' => in_array($addon->id, $selectedAddons, true),
                    'border-zinc-200 dark:border-zinc-800' => ! in_array($addon->id, $selectedAddons, true),
                ])>
                    <div>
                        <div class="font-medium">{{ $addon->title }}</div>
                        @if ($addon->subtitle) <div class="text-sm text-zinc-500">{{ $addon->subtitle }}</div> @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-zinc-500">+${{ number_format((float) $addon->monthly, 2) }}/mo</span>
                        <input type="checkbox" wire:click="toggleAddon({{ $addon->id }})" @checked(in_array($addon->id, $selectedAddons, true))>
                    </div>
                </label>
            @endforeach
        </div>
        <div class="mt-6 flex gap-3">
            <button type="button" wire:click="$set('step', 3)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
            <button type="button" wire:click="toReview" class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
        </div>
    @endif

    {{-- Step 5 — term, promo, review --}}
    @if ($step === 5)
        <h1 class="text-2xl font-bold">{{ __('Review your order') }}</h1>

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach (config('checkout.terms') as $code => $cfg)
                <button wire:click="setTerm({{ $code }})" @class([
                    'rounded-lg border px-4 py-2 text-sm font-medium',
                    'border-blue-600 bg-blue-600 text-white' => $term === $code,
                    'border-zinc-300 dark:border-zinc-700' => $term !== $code,
                ])>{{ __($cfg['label']) }}</button>
            @endforeach
        </div>

        @if ($this->breakdown)
            <dl class="mt-6 space-y-2 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-800">
                <div class="flex justify-between"><dt>{{ __('Plan') }}</dt><dd>${{ number_format($this->breakdown->packagePrice, 2) }}</dd></div>
                @if ($this->breakdown->addonPrice > 0)
                    <div class="flex justify-between"><dt>{{ __('Add-ons') }}</dt><dd>${{ number_format($this->breakdown->addonPrice, 2) }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700"><dt>{{ __('Subtotal') }}</dt><dd>${{ number_format($this->breakdown->subtotal, 2) }}</dd></div>
                @if ($this->breakdown->isDiscounted())
                    <div class="flex justify-between text-green-600"><dt>{{ __('Promo') }} ({{ $promo['name'] }})</dt><dd>-${{ number_format($this->breakdown->discount, 2) }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-bold dark:border-zinc-700">
                    <dt>{{ __('Due today') }}</dt><dd>${{ number_format($this->breakdown->payable, 2) }}</dd>
                </div>
                @unless ($this->breakdown->recurring)
                    <p class="pt-1 text-xs text-zinc-500">{{ __('One-time prepaid charge — no recurring billing.') }}</p>
                @endunless
            </dl>
        @endif

        <div class="mt-4">
            @if ($promo)
                <div class="flex items-center gap-3 text-sm">
                    <span class="rounded bg-green-100 px-2 py-1 font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">{{ $promo['name'] }}</span>
                    <button wire:click="removePromo" class="text-zinc-500 underline">{{ __('Remove') }}</button>
                </div>
            @else
                <form wire:submit="applyPromo" class="flex gap-2">
                    <input type="text" wire:model="promoCode" placeholder="{{ __('Promo code') }}"
                           class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <button class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold dark:border-zinc-700">{{ __('Apply') }}</button>
                </form>
                @error('promoCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @endif
        </div>

        <div class="mt-8 flex gap-3">
            <button type="button" wire:click="$set('step', 4)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
            <button type="button" wire:click="goToPayment" class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue to payment') }}</button>
        </div>
    @endif
</div>
