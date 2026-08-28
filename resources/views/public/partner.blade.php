<x-layouts::public :title="__('Partner network')">
    <div class="mx-auto max-w-2xl px-4 py-16">
        <h1 class="text-3xl font-bold">{{ __('Partner network') }}</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('Tell us about yourself and we will reach out.') }}</p>
        <form method="POST" action="{{ route('partner.store') }}" class="mt-8 grid gap-4 sm:grid-cols-2">
            @csrf
            <input name="first_name" placeholder="{{ __('First name') }}" required class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="last_name" placeholder="{{ __('Last name') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="company_name" placeholder="{{ __('Company') }}" class="rounded-md border-zinc-300 sm:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="email" type="email" placeholder="{{ __('Email') }}" required class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="primary_phone" placeholder="{{ __('Primary phone') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="mobile_number" placeholder="{{ __('Mobile') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="fax" placeholder="{{ __('Fax') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="street_address" placeholder="{{ __('Street address') }}" class="rounded-md border-zinc-300 sm:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="city" placeholder="{{ __('City') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="state" placeholder="{{ __('State') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <input name="zip" placeholder="{{ __('ZIP') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900">
            <textarea name="comment" rows="4" placeholder="{{ __('Comment') }}" class="rounded-md border-zinc-300 sm:col-span-2 dark:border-zinc-700 dark:bg-zinc-900"></textarea>
            @error('email') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
            <div class="sm:col-span-2">
                <button class="rounded-lg bg-blue-600 px-5 py-2.5 font-semibold text-white hover:bg-blue-700">{{ __('Submit') }}</button>
            </div>
        </form>
    </div>
</x-layouts::public>
