@props(['active' => null])
@php
    $links = [
        'dashboard' => ['label' => __('Overview'), 'route' => 'portal.dashboard'],
        'package' => ['label' => __('My plan'), 'route' => 'portal.package'],
        'claims' => ['label' => __('Claims'), 'route' => 'portal.claims.index'],
        'agreement' => ['label' => __('Agreement'), 'route' => 'portal.agreement'],
        'profile' => ['label' => __('Profile'), 'route' => 'portal.profile'],
    ];
@endphp
<nav class="mb-8 flex flex-wrap gap-1 border-b border-zinc-200 text-sm dark:border-zinc-800">
    @foreach ($links as $key => $link)
        @if (\Illuminate\Support\Facades\Route::has($link['route']))
            <a href="{{ route($link['route']) }}" wire:navigate @class([
                '-mb-px border-b-2 px-4 py-2.5 font-medium',
                'border-blue-600 text-blue-600' => $active === $key,
                'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $active !== $key,
            ])>{{ $link['label'] }}</a>
        @endif
    @endforeach
</nav>
