<div class="mx-auto max-w-md px-4 py-16">
    <h1 class="text-2xl font-bold">{{ __('Agent sign in') }}</h1>
    <p class="mt-1 text-sm text-zinc-500">{{ __('Real Estate order portal.') }}</p>

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
        <button class="w-full rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700">{{ __('Sign in') }}</button>
    </form>

    <p class="mt-6 text-sm text-zinc-500">
        {{ __('New agent?') }} <a href="{{ route('agent.register') }}" wire:navigate class="text-blue-600 underline">{{ __('Create an account') }}</a>
    </p>
</div>
