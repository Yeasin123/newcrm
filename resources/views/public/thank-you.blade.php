<x-layouts::public :title="__('Thank you')">
    <div class="mx-auto max-w-xl px-4 py-24 text-center">
        <h1 class="text-3xl font-bold">{{ __('Thank you!') }}</h1>
        <p class="mt-4 text-zinc-600 dark:text-zinc-400">{{ __('We have received your request. A member of our team will follow up shortly.') }}</p>
        <a href="{{ route('homepage') }}" class="mt-8 inline-block rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700">{{ __('Back to home') }}</a>
    </div>
</x-layouts::public>
