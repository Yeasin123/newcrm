<div class="mx-auto max-w-4xl px-4 py-12">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">{{ __('Welcome, :name', ['name' => $agent->name]) }}</h1>
        <div class="flex items-center gap-4 text-sm">
            <a href="{{ route('realestate.settings') }}" wire:navigate class="text-zinc-500 underline">{{ __('Settings') }}</a>
            <button wire:click="logout" class="text-zinc-500 underline">{{ __('Sign out') }}</button>
        </div>
    </div>

    <div class="mt-8 flex items-center justify-between rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
        <div>
            <div class="text-sm text-zinc-500">{{ __('Total orders') }}</div>
            <div class="text-2xl font-bold">{{ $orderCount }}</div>
        </div>
        @if (\Illuminate\Support\Facades\Route::has('agent.order.create'))
            <a href="{{ route('agent.order.create') }}" wire:navigate
               class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('New order') }}</a>
        @endif
    </div>

    <h2 class="mt-10 text-lg font-semibold">{{ __('Recent orders') }}</h2>
    <div class="mt-3 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-zinc-500 dark:bg-white/5">
                <tr><th class="px-4 py-2">{{ __('Date') }}</th><th class="px-4 py-2">{{ __('Plan') }}</th><th class="px-4 py-2">{{ __('Buyer') }}</th><th class="px-4 py-2">{{ __('Property') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-t border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-2">{{ $order->created_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $order->plan_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $order->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $order->address_one ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('No orders yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
