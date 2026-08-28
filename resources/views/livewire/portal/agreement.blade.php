<div class="mx-auto max-w-3xl px-4 py-12">
    <h1 class="text-2xl font-bold">{{ __('Your agreement') }}</h1>
    <x-portal-nav active="agreement" />

    <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-800">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-zinc-500">{{ __('Policy number') }}</dt>
                <dd class="font-medium">{{ $client->policy_number }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-zinc-500">{{ __('Signature status') }}</dt>
                <dd class="font-medium">
                    @if ($signed)
                        <span class="text-green-600">{{ __('Signed') }}</span>
                        <span class="text-zinc-500">&middot; {{ $client->signature_date_time }}</span>
                    @else
                        <span class="text-amber-600">{{ __('Not signed') }}</span>
                    @endif
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-zinc-500">{{ __('Plan') }}</dt>
                <dd class="font-medium">{{ $client->propertyPackage->title ?? '—' }}</dd>
            </div>
        </dl>

        @if ($latestRenewalPdf)
            <div class="mt-6 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
                <p class="text-zinc-500">{{ __('Latest renewal agreement on file:') }}
                    {{ $latestRenewalPdf->signature_date_time ?? $latestRenewalPdf->created_at?->format('M j, Y') }}</p>
            </div>
        @endif
    </div>

    <p class="mt-6 text-sm text-zinc-500">
        {{ __('Need a copy of your full policy document or want to re-sign? Contact support and we will email it to you.') }}
    </p>
</div>
