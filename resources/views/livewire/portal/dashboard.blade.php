<div class="mx-auto max-w-4xl px-4 py-12">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">{{ __('Welcome back, :name', ['name' => $client->first_name]) }}</h1>
        <button wire:click="logout" class="text-sm text-zinc-500 underline">{{ __('Sign out') }}</button>
    </div>

    <x-portal-nav active="dashboard" />

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('Policy number') }}</dt>
            <dd class="mt-1 text-lg font-semibold">{{ $client->policy_number }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('Status') }}</dt>
            <dd class="mt-1 text-lg font-semibold">{{ (int) $client->status === 1 ? __('Active') : __('Suspended') }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('Plan') }}</dt>
            <dd class="mt-1 text-lg font-semibold">{{ $planTitle }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('Billing') }}</dt>
            <dd class="mt-1 text-lg font-semibold">
                {{ $structure }}@if ($amount) &middot; ${{ number_format((float) str_replace(',', '', $amount), 2) }} @endif
            </dd>
        </div>
    </dl>

    <div class="mt-4 flex flex-wrap gap-3 text-sm">
        @if ($nextBill)
            <span class="rounded-lg bg-zinc-100 px-3 py-1.5 dark:bg-white/5">{{ __('Next bill') }}: {{ $nextBill->format('M j, Y') }}</span>
        @endif
        <span class="rounded-lg bg-zinc-100 px-3 py-1.5 dark:bg-white/5">{{ __('Open claims') }}: {{ $openClaims }}</span>
    </div>

    <h2 class="mt-10 text-lg font-semibold">{{ __('Recent payments') }}</h2>
    <div class="mt-3 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-zinc-500 dark:bg-white/5">
                <tr><th class="px-4 py-2">{{ __('Date') }}</th><th class="px-4 py-2">{{ __('Purpose') }}</th><th class="px-4 py-2 text-right">{{ __('Amount') }}</th><th class="px-4 py-2">{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($recentPayments as $p)
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
