<x-layouts::app :title="$title">
    <div class="flex h-full w-full flex-1 flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
        <flux:icon name="wrench-screwdriver" class="size-10 text-zinc-400" />
        <flux:heading size="xl">{{ $title }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ __('This module is part of Phase :phase of the rebuild and is not built yet.', ['phase' => $phase]) }}
        </flux:text>
        <flux:button :href="route('dashboard')" wire:navigate variant="ghost" size="sm">
            {{ __('Back to dashboard') }}
        </flux:button>
    </div>
</x-layouts::app>
