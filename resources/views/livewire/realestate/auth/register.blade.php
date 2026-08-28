<div class="mx-auto max-w-lg px-4 py-16">
    <h1 class="text-2xl font-bold">{{ __('Agent sign up') }}</h1>
    <p class="mt-1 text-sm text-zinc-500">{{ __('Step :n of 2', ['n' => $step]) }}</p>

    @if ($step === 1)
        <form wire:submit="stepOne" class="mt-6 space-y-4">
            <div>
                <label class="text-sm font-medium">{{ __('Email') }}</label>
                <input type="email" wire:model="email" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">{{ __('Role') }}</label>
                <input type="text" wire:model="role" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                @error('role') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">{{ __('Company') }}</label>
                <select wire:model="company_id" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="">{{ __('— pick or type a new one below —') }}</option>
                    @foreach ($this->companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model="company_name" placeholder="{{ __('New company name') }}"
                       class="mt-2 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                @error('company_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium">{{ __('Password') }}</label>
                    <input type="password" wire:model="password" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Confirm password') }}</label>
                    <input type="password" wire:model="confirmPassword" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error('confirmPassword') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Continue') }}</button>
        </form>
    @else
        <form wire:submit="register" class="mt-6 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'name' => __('Full name'), 'second_email' => __('Secondary email'),
                    'address_one' => __('Address line 1'), 'address_two' => __('Address line 2'),
                    'city' => __('City'), 'state' => __('State'),
                    'postal_code' => __('Postal code'), 'office_number' => __('Office number'),
                    'mobile_number' => __('Mobile number'),
                ] as $field => $label)
                    <div @class(['sm:col-span-2' => str_starts_with($field, 'address')])>
                        <label class="text-sm font-medium">{{ $label }}</label>
                        <input type="text" wire:model="{{ $field }}" class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                        @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            <div class="flex gap-3">
                <button type="button" wire:click="$set('step', 1)" class="rounded-lg border border-zinc-300 px-5 py-2.5 font-semibold dark:border-zinc-700">{{ __('Back') }}</button>
                <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Create account') }}</button>
            </div>
        </form>
    @endif
</div>
