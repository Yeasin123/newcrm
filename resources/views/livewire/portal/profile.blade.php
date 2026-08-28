<div class="mx-auto max-w-3xl px-4 py-12">
    <h1 class="text-2xl font-bold">{{ __('Profile') }}</h1>
    <x-portal-nav active="profile" />

    @if (session('success'))
        <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300">{{ session('success') }}</div>
    @endif

    <form wire:submit="saveProfile" class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-800">
        <h2 class="font-semibold">{{ __('Your details') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'first_name' => __('First name'), 'last_name' => __('Last name'),
                'email' => __('Email'), 'contact' => __('Phone'),
                'address' => __('Address'), 'city' => __('City'),
                'state' => __('State'), 'zip_code' => __('ZIP'),
            ] as $field => $label)
                <div @class(['sm:col-span-2' => $field === 'address'])>
                    <label class="text-sm font-medium">{{ $label }}</label>
                    <input type="text" wire:model="{{ $field }}"
                           class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                    @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Save changes') }}</button>
    </form>

    <form wire:submit="changePassword" class="mt-8 space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-800">
        <h2 class="font-semibold">{{ __('Change password') }}</h2>
        <div>
            <label class="text-sm font-medium">{{ __('Current password') }}</label>
            <input type="password" wire:model="current_password" autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('current_password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium">{{ __('New password') }}</label>
                <input type="password" wire:model="password" autocomplete="new-password"
                       class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">{{ __('Confirm new password') }}</label>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password"
                       class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            </div>
        </div>
        <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Update password') }}</button>
    </form>
</div>
