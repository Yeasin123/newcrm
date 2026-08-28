<div class="w-full max-w-2xl">
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('customers.index') }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-black/5 dark:hover:bg-white/5">
            <flux:icon name="arrow-left" variant="outline" class="size-5" />
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-navy-900)] dark:text-white">{{ __('Import Customers') }}</h1>
            <p class="mt-0.5 text-sm text-zinc-500">{{ __('CSV with a header row. Column names that match a customer field are imported.') }}</p>
        </div>
    </div>

    @if ($imported !== null)
        <div class="mb-5 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ __('Imported :n customer(s).', ['n' => number_format($imported)]) }}
            @if ($skipped) {{ __(':n row(s) skipped (no name or email).', ['n' => number_format($skipped)]) }} @endif
            <a href="{{ route('customers.index') }}" wire:navigate class="ml-1 font-semibold underline">{{ __('View customers') }}</a>
        </div>
    @endif

    @if ($error)
        <div class="mb-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">{{ $error }}</div>
    @endif

    <form wire:submit="import" class="space-y-4 rounded-xl border border-[var(--color-hairline)] bg-[var(--color-surface)] p-6">
        <div>
            <label class="block text-sm font-semibold text-[var(--color-navy-900)] dark:text-zinc-200">{{ __('CSV file') }}</label>
            <input type="file" wire:model="file" accept=".csv,text/csv"
                class="mt-2 block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700 dark:text-zinc-300">
            @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            <div wire:loading wire:target="file" class="mt-2 text-sm text-zinc-500">{{ __('Reading file…') }}</div>
        </div>

        @if ($preview)
            <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-white/5">
                <p class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('Detected columns:') }}</p>
                <p class="mt-1 text-zinc-500">{{ implode(', ', $preview) }}</p>
            </div>
        @endif

        <button type="submit" wire:loading.attr="disabled" wire:target="import"
            class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="import">{{ __('Import') }}</span>
            <span wire:loading wire:target="import">{{ __('Importing…') }}</span>
        </button>
    </form>
</div>
