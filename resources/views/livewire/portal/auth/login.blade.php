<div class="mx-auto max-w-md px-4 py-16">
    <h1 class="text-2xl font-bold">{{ __('Customer sign in') }}</h1>
    <p class="mt-1 text-sm text-zinc-500">{{ __('Access your plan, agreement and claims.') }}</p>

    <form wire:submit="login" class="mt-6 space-y-4">
        <div>
            <label class="text-sm font-medium">{{ __('Email') }}</label>
            <input type="email" wire:model="email" autocomplete="email"
                   class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="text-sm font-medium">{{ __('Password') }}</label>
            <input type="password" wire:model="password" autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border border-zinc-300 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
            @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button class="w-full rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700">
            {{ __('Sign in') }}
        </button>
    </form>

    <details class="mt-8 text-sm">
        <summary class="cursor-pointer text-zinc-500">{{ __('Forgot your password?') }}</summary>
        <form wire:submit="findPolicy" class="mt-3 space-y-2">
            <input type="text" wire:model="lookupPolicy" placeholder="{{ __('Policy number') }}"
                   class="w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
            @error('lookupPolicy') <p class="text-red-600">{{ $message }}</p> @enderror
            <button class="rounded-lg border border-zinc-300 px-4 py-2 font-semibold dark:border-zinc-700">{{ __('Look up') }}</button>
            @if ($lookupResult)
                <p class="text-green-700 dark:text-green-400">{{ $lookupResult }}</p>
            @endif
        </form>
    </details>
</div>
