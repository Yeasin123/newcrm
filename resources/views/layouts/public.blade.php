@props(['title' => null])
@php
    $bs = \App\Models\BasicSettings::first();
    $banner = \App\Models\Banner::first();
    $nav = [
        ['label' => __('Plans'), 'route' => 'shop.planes'],
        ['label' => __('About'), 'route' => 'about.us'],
        ['label' => __('Blog'), 'route' => 'blogs.index'],
        ['label' => __('Partner'), 'route' => 'partner'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name', 'American Home Coverage') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @if ($banner && $banner->promo_code)
        <div class="w-full px-4 py-2 text-center text-sm" style="background: {{ $banner->bg_color ?: '#111827' }}; color: {{ $banner->text_one_color ?: '#fff' }}">
            <span class="font-semibold">{{ $banner->promo_code }}</span>
            @if ($banner->percent_off) &middot; {{ $banner->percent_off }} @endif
            @if ($banner->text_two) &middot; {{ $banner->text_two }} @endif
        </div>
    @endif

    <header class="border-b border-zinc-200 dark:border-zinc-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('homepage') }}" class="text-lg font-bold tracking-tight">
                {{ config('app.name', 'American Home Coverage') }}
            </a>
            <nav class="hidden items-center gap-6 text-sm font-medium md:flex">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" class="hover:text-blue-600">{{ $item['label'] }}</a>
                @endforeach
                <a href="{{ route('login.user') }}" class="rounded-md bg-blue-600 px-3 py-1.5 text-white hover:bg-blue-700">
                    {{ __('Customer login') }}
                </a>
            </nav>
        </div>
    </header>

    @if (session('success'))
        <div class="mx-auto mt-4 max-w-6xl px-4">
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300">
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="mx-auto mt-4 max-w-6xl px-4">
            <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-300">
                {{ session('error') }}
            </div>
        </div>
    @endif

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-zinc-200 py-10 text-sm text-zinc-500 dark:border-zinc-800">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 sm:flex-row sm:items-center sm:justify-between">
            <div>&copy; {{ date('Y') }} {{ config('app.name', 'American Home Coverage') }}. {{ $bs?->footer_text }}</div>
            <div class="flex gap-4">
                @if ($bs?->footer_number)<span>{{ $bs->footer_number }}</span>@endif
                <a href="{{ route('privacy.policy') }}" class="hover:text-blue-600">{{ __('Privacy') }}</a>
                <a href="{{ route('terms.conditions') }}" class="hover:text-blue-600">{{ __('Terms') }}</a>
                <a href="{{ route('sample.policy') }}" class="hover:text-blue-600">{{ __('Sample policy') }}</a>
            </div>
        </div>
    </footer>
</body>
</html>
