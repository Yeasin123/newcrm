<div class="mx-auto max-w-2xl px-4 py-12">
    <a href="{{ route('realesate.home') }}" wire:navigate class="text-sm text-zinc-500 underline">&larr; {{ __('Dashboard') }}</a>
    <h1 class="mt-2 text-2xl font-bold">{{ __('Order #:id', ['id' => $order->id]) }}</h1>

    <dl class="mt-6 space-y-2 rounded-xl border border-zinc-200 p-5 text-sm dark:border-zinc-800">
        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Plan') }}</dt><dd class="font-medium">{{ $order->plan_name }}</dd></div>
        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Buyer') }}</dt><dd>{{ $order->name }}</dd></div>
        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Buyer email') }}</dt><dd>{{ $order->email }}</dd></div>
        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Buyer phone') }}</dt><dd>{{ $order->phone }}</dd></div>
        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Property') }}</dt><dd>{{ $order->address_one }} {{ $order->address_two }}</dd></div>
        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Placed') }}</dt><dd>{{ $order->created_at?->format('M j, Y') }}</dd></div>
    </dl>

    @if ($order->addondata)
        <h2 class="mt-6 text-sm font-semibold text-zinc-500">{{ __('Add-ons') }}</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($order->addondata as $addon)
                <li class="flex justify-between"><span>{{ $addon['name'] ?? '' }}</span><span class="text-zinc-500">${{ number_format((float) ($addon['price'] ?? 0), 2) }}</span></li>
            @endforeach
        </ul>
    @endif

    @foreach (['closingInfo' => __('Closing / escrow'), 'buyerAgent' => __("Buyer's agent"), 'sellerAgent' => __("Seller's agent")] as $key => $label)
        @if ($order->{$key})
            <div class="mt-4 rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                <p class="font-semibold">{{ $label }}</p>
                <p class="text-zinc-500">{{ $order->{$key}['name'] ?? '' }} &middot; {{ $order->{$key}['email'] ?? '' }}</p>
            </div>
        @endif
    @endforeach
</div>
