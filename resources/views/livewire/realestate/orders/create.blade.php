<div class="mx-auto max-w-2xl px-4 py-12">
    <h1 class="text-2xl font-bold">{{ __('New order') }}</h1>
    <ol class="mb-8 mt-4 flex flex-wrap gap-2 text-xs text-zinc-400">
        @foreach (['Property', 'Plan', 'Buyer', 'Add-ons', 'Closing', 'Review'] as $i => $label)
            <li @class(['font-semibold text-blue-600' => $step === $i + 1])>{{ $i + 1 }}. {{ __($label) }}</li>
        @endforeach
    </ol>

    @if ($step === 1)
        <form wire:submit="stepProperty" class="space-y-4">
            <input type="text" wire:model="address_one" placeholder="{{ __('Address line 1') }}" class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('address_one') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <input type="text" wire:model="address_two" placeholder="{{ __('Address line 2') }}" class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-4 sm:grid-cols-3">
                <input type="text" wire:model="city" placeholder="{{ __('City') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                <input type="text" wire:model="state" placeholder="{{ __('State') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                <input type="text" wire:model="postal_code" placeholder="{{ __('Postal code') }}" class="rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            </div>
            @error('city') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('state') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('postal_code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="grid gap-4 sm:grid-cols-2">
                <select wire:model="property_size" class="rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="">{{ __('Property size') }}</option>
                    @foreach ($this->propertySizes as $s) <option value="{{ $s->id }}">{{ $s->title }}</option> @endforeach
                </select>
                <select wire:model="property_name" class="rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="">{{ __('Property type') }}</option>
                    @foreach ($this->propertyTypes as $t) <option value="{{ $t->id }}">{{ $t->title }}</option> @endforeach
                </select>
            </div>
            @error('property_size') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('property_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
        </form>
    @elseif ($step === 2)
        <form wire:submit="stepPlan" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($this->packages as $package)
                    <button type="button" wire:click="$set('planId', {{ $package->id }})" @class([
                        'rounded-xl border p-4 text-left',
                        'border-blue-600 ring-2 ring-blue-600' => $planId === $package->id,
                        'border-zinc-200 dark:border-zinc-800' => $planId !== $package->id,
                    ])>
                        <div class="font-semibold">{{ $package->title }}</div>
                        <div class="mt-1 text-sm text-zinc-500">${{ number_format((float) ($package->yearly ?: $package->monthly), 2) }}/yr</div>
                    </button>
                @endforeach
            </div>
            @error('planId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex gap-3">
                <button type="button" wire:click="$set('step', 1)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
                <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
            </div>
        </form>
    @elseif ($step === 3)
        <form wire:submit="stepBuyer" class="space-y-4">
            <input type="text" wire:model="buyer_name" placeholder="{{ __('Buyer name') }}" class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('buyer_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <input type="email" wire:model="buyer_email" placeholder="{{ __('Buyer email') }}" class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('buyer_email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <input type="text" wire:model="buyer_phone" placeholder="{{ __('Buyer phone') }}" class="w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('buyer_phone') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex gap-3">
                <button type="button" wire:click="$set('step', 2)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
                <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
            </div>
        </form>
    @elseif ($step === 4)
        <div class="space-y-3">
            @foreach ($this->addons as $addon)
                <label class="flex items-center justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <span>{{ $addon->title }}</span>
                    <span class="flex items-center gap-3 text-sm text-zinc-500">
                        ${{ number_format((float) ($addon->yearly ?: $addon->monthly), 2) }}
                        <input type="checkbox" value="{{ $addon->id }}" wire:model="selectedAddons">
                    </span>
                </label>
            @endforeach
            <div class="flex gap-3 pt-2">
                <button type="button" wire:click="$set('step', 3)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
                <button type="button" wire:click="stepOptions" class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
            </div>
        </div>
    @elseif ($step === 5)
        <form wire:submit="stepClosing" class="space-y-6">
            @foreach ([
                ['closing_name', 'closing_email', __('Closing / escrow')],
                ['buyer_agent_name', 'buyer_agent_email', __("Buyer's agent")],
                ['seller_agent_name', 'seller_agent_email', __("Seller's agent")],
            ] as [$nameField, $emailField, $label])
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <p class="text-sm font-semibold">{{ $label }} <span class="font-normal text-zinc-400">({{ __('optional') }})</span></p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <input type="text" wire:model="{{ $nameField }}" placeholder="{{ __('Name') }}" class="rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                        <input type="email" wire:model="{{ $emailField }}" placeholder="{{ __('Email') }}" class="rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                    </div>
                    @error($emailField) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div class="flex gap-3">
                <button type="button" wire:click="$set('step', 4)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
                <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
            </div>
        </form>
    @else
        <div class="space-y-4">
            <dl class="space-y-2 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-800">
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Property') }}</dt><dd>{{ $address_one }}, {{ $city }} {{ $state }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Buyer') }}</dt><dd>{{ $buyer_name }}</dd></div>
                <div class="flex justify-between border-t border-zinc-200 pt-2 font-bold dark:border-zinc-700"><dt>{{ __('Order total') }}</dt><dd>${{ number_format($this->total, 2) }}/yr</dd></div>
            </dl>
            <p class="text-xs text-zinc-500">{{ __('Placing the order creates a pending policy. A representative will contact the buyer to collect payment.') }}</p>
            <div class="flex gap-3">
                <button type="button" wire:click="$set('step', 5)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
                <button type="button" wire:click="placeOrder" class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Place order') }}</button>
            </div>
        </div>
    @endif
</div>
