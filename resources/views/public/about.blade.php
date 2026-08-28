<x-layouts::public :title="__('About us')">
    <div class="mx-auto max-w-3xl px-4 py-16">
        <h1 class="text-3xl font-bold">{{ $bs?->about_title ?: __('About American Home Coverage') }}</h1>
        <div class="prose prose-zinc mt-6 max-w-none dark:prose-invert">
            {!! nl2br(e($bs?->about_text ?: __('We help homeowners avoid unexpected repair costs with straightforward home-warranty plans and fast, no-nonsense claims.'))) !!}
        </div>

        <h2 class="mt-12 text-xl font-semibold">{{ __('Contact us') }}</h2>
        <form method="POST" action="{{ route('about.us.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <input name="first_name" placeholder="{{ __('First name') }}" required class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="last_name" placeholder="{{ __('Last name') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="email" type="email" placeholder="{{ __('Email') }}" required class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="contact" placeholder="{{ __('Phone') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <textarea name="description" rows="4" placeholder="{{ __('How can we help?') }}" class="rounded-md border-zinc-300 sm:col-span-2 dark:border-zinc-700 dark:bg-zinc-900"></textarea>
            @error('email') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
            <div class="sm:col-span-2">
                <button class="rounded-lg bg-blue-600 px-5 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Send') }}</button>
            </div>
        </form>
    </div>
</x-layouts::public>
