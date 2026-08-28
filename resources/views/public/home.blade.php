<x-layouts::public :title="__('Home warranty coverage that just works')">
    <section class="bg-gradient-to-b from-blue-50 to-white dark:from-blue-500/5 dark:to-zinc-950">
        <div class="mx-auto max-w-6xl px-4 py-20 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">
                {{ $bs?->promotion_heading ?: __('Protect your home. Skip the surprise repair bills.') }}
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
                {{ $bs?->promotion_text_one ?: __('One plan covers the systems and appliances you rely on every day. File a claim in minutes and we handle the rest.') }}
            </p>
            <div class="mt-8 flex items-center justify-center gap-3">
                <a href="{{ route('shop.planes') }}" class="rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700">
                    {{ $bs?->button_text_one ?: __('See plans & pricing') }}
                </a>
                <a href="{{ route('about.us') }}" class="rounded-lg border border-zinc-300 px-6 py-3 font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-white/5">
                    {{ __('How it works') }}
                </a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16">
        <div class="grid gap-8 sm:grid-cols-3">
            @foreach ([
                [$bs?->step_one ?: __('Pick a plan'), __('Choose the coverage that fits your home and budget.')],
                [$bs?->step_two ?: __('File a claim'), __('Something broke? Tell us online any time, day or night.')],
                [$bs?->step_three ?: __('We fix it'), __('A vetted technician is dispatched and the covered cost is handled.')],
            ] as $i => [$title, $text])
                <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-800">
                    <div class="text-sm font-semibold text-blue-600">{{ __('Step') }} {{ $i + 1 }}</div>
                    <h3 class="mt-2 text-lg font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-t border-zinc-200 bg-zinc-50 py-16 dark:border-zinc-800 dark:bg-white/5">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:grid-cols-3 sm:text-center">
            @foreach ([
                [$bs?->monthly_customer, __('Customers protected')],
                [$bs?->monthly_claim, __('Claims handled')],
                [$bs?->monthly_bill, __('Avg. covered repair')],
            ] as [$value, $label])
                <div>
                    <div class="text-3xl font-extrabold">{{ $value ?: '—' }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts::public>
