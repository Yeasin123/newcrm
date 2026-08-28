<x-layouts::public :title="__('Blog')">
    <div class="mx-auto max-w-5xl px-4 py-16">
        <h1 class="text-3xl font-bold">{{ __('Blog') }}</h1>
        <div class="mt-8 grid gap-8 sm:grid-cols-2">
            @forelse ($blogs as $blog)
                <a href="{{ route('blogs.show', $blog) }}" class="block rounded-xl border border-zinc-200 p-6 hover:border-blue-400 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold">{{ $blog->title }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($blog->excerpt ?: strip_tags($blog->content), 160) }}</p>
                    <p class="mt-3 text-xs text-zinc-400">{{ optional($blog->published_at)->format('M j, Y') }}</p>
                </a>
            @empty
                <p class="text-zinc-500">{{ __('No posts yet.') }}</p>
            @endforelse
        </div>
        <div class="mt-8">{{ $blogs->links() }}</div>
    </div>
</x-layouts::public>
