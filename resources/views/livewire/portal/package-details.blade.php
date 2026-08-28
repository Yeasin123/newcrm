<div class="mx-auto max-w-4xl px-4 py-12">
    <h1 class="text-2xl font-bold">{{ __('My plan') }}</h1>
    <x-portal-nav active="package" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <h2 class="font-semibold">{{ $planTitle }}</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Billing') }}</dt><dd>{{ $structure }}</dd></div>
                @if ($subtotal)
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Plan price') }}</dt><dd>${{ number_format((float) str_replace(',', '', $subtotal), 2) }}</dd></div>
                @endif
                @if ($discount)
                    <div class="flex justify-between text-green-600"><dt>{{ __('Discount') }}</dt><dd>{{ $discount }}</dd></div>
                @endif
                @if ($payable)
                    <div class="flex justify-between border-t border-zinc-200 pt-2 font-semibold dark:border-zinc-700"><dt>{{ __('You pay') }}</dt><dd>${{ number_format((float) str_replace(',', '', $payable), 2) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Start date') }}</dt><dd>{{ $startDate?->format('M j, Y') ?? '—' }}</dd></div>
                @if ($endDate)
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('End date') }}</dt><dd>{{ $endDate->format('M j, Y') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <h2 class="font-semibold">{{ __('Add-ons') }}</h2>
            @if ($addons->isEmpty())
                <p class="mt-3 text-sm text-zinc-500">{{ __('No add-ons on this plan.') }}</p>
            @else
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($addons as $addon)
                        <li class="flex justify-between">
                            <span>{{ $addon['title'] }}</span>
                            <span class="text-zinc-500">{{ $addon['currency'] }}{{ number_format($addon['price'], 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <h2 class="mt-10 text-lg font-semibold">{{ __('Payment history') }}</h2>
    <div class="mt-3 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-zinc-500 dark:bg-white/5">
                <tr><th class="px-4 py-2">{{ __('Date') }}</th><th class="px-4 py-2">{{ __('Purpose') }}</th><th class="px-4 py-2 text-right">{{ __('Amount') }}</th><th class="px-4 py-2">{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr class="border-t border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-2">{{ $p->payment_date ? \Illuminate\Support\Carbon::parse($p->payment_date)->format('M j, Y') : '—' }}</td>
                        <td class="px-4 py-2">{{ $p->purpose }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format((float) $p->amount, 2) }}</td>
                        <td class="px-4 py-2">{{ (int) $p->payment_status === 1 ? __('Paid') : __('Unpaid') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-zinc-500">{{ __('No payments yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
