<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Welcome back, :name.', ['name' => auth()->user()->first_name ?: auth()->user()->name]) }}
            </flux:text>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            @foreach ([
                ['label' => __('Active Customers'), 'icon' => 'users'],
                ['label' => __('Open Leads'), 'icon' => 'funnel'],
                ['label' => __('MRR'), 'icon' => 'currency-dollar'],
            ] as $tile)
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-zinc-500">{{ $tile['label'] }}</flux:text>
                        <flux:icon :name="$tile['icon']" class="size-5 text-zinc-400" />
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-zinc-400">&mdash;</div>
                </div>
            @endforeach
        </div>

        <div class="relative flex h-full flex-1 items-center justify-center rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:text class="text-zinc-500">
                {{ __('Full dashboard (charts, pipeline, salesman performance) arrives in Phase 10.') }}
            </flux:text>
        </div>
    </div>
</x-layouts::app>
