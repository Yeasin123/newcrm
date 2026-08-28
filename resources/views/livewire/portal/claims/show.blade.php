<div class="mx-auto max-w-3xl px-4 py-12">
    <a href="{{ route('portal.claims.index') }}" wire:navigate class="text-sm text-zinc-500 underline">&larr; {{ __('All claims') }}</a>
    <h1 class="mt-2 text-2xl font-bold">{{ __('Claim :no', ['no' => $claim->claim_no]) }}</h1>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('System') }}</dt>
            <dd class="mt-1 font-medium">{{ $claim->systemRef->title ?? '—' }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('Brand') }}</dt>
            <dd class="mt-1 font-medium">{{ $claim->brandRef->title ?? '—' }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('Issue') }}</dt>
            <dd class="mt-1 font-medium">{{ $claim->issueRef->title ?? '—' }}</dd>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <dt class="text-sm text-zinc-500">{{ __('Status') }}</dt>
            <dd class="mt-1 font-medium">{{ \App\Enums\ClaimStatus::tryFrom((int) $claim->status)?->label() ?? __('Open') }}</dd>
        </div>
    </dl>

    <div class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
        <h2 class="text-sm font-semibold text-zinc-500">{{ __('What happened') }}</h2>
        <p class="mt-2 whitespace-pre-line">{{ $claim->problem }}</p>
        @if ($claim->last_time_working_detail)
            <p class="mt-3 text-sm text-zinc-500">{{ __('Last working:') }} {{ $claim->last_time_working_detail }}</p>
        @endif
    </div>

    @if ($claim->tracks->isNotEmpty())
        <h2 class="mt-8 text-lg font-semibold">{{ __('Activity') }}</h2>
        <ol class="mt-3 space-y-3 border-l border-zinc-200 pl-5 dark:border-zinc-800">
            @foreach ($claim->tracks->sortByDesc('id') as $track)
                <li>
                    <div class="text-sm">{{ $track->note ?? $track->title ?? __('Update') }}</div>
                    <div class="text-xs text-zinc-500">{{ $track->created_at?->format('M j, Y g:i A') }}</div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
