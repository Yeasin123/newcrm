<div class="mx-auto max-w-4xl px-4 py-12">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">{{ __('Claims') }}</h1>
        <a href="{{ route('portal.claims.create') }}" wire:navigate
           class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('File a claim') }}</a>
    </div>
    <x-portal-nav active="claims" />

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-zinc-500 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-2">{{ __('Claim #') }}</th>
                    <th class="px-4 py-2">{{ __('System') }}</th>
                    <th class="px-4 py-2">{{ __('Filed') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($claims as $claim)
                    <tr class="border-t border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-2 font-medium">{{ $claim->claim_no }}</td>
                        <td class="px-4 py-2">{{ $claim->systemRef->title ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $claim->created_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-2">{{ \App\Enums\ClaimStatus::tryFrom((int) $claim->status)?->label() ?? __('Open') }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('portal.claims.show', $claim->id) }}" wire:navigate class="text-blue-600 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('You have not filed any claims yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
