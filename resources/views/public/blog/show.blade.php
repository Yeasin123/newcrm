<x-layouts::public :title="$blog->title">
    <article class="mx-auto max-w-3xl px-4 py-16">
        <a href="{{ route('blogs.index') }}" class="text-sm text-blue-600 hover:underline">&larr; {{ __('All posts') }}</a>
        <h1 class="mt-4 text-3xl font-bold">{{ $blog->title }}</h1>
        <p class="mt-2 text-sm text-zinc-400">{{ optional($blog->published_at)->format('M j, Y') }}</p>
        <div class="prose prose-zinc mt-8 max-w-none dark:prose-invert">{!! $blog->content !!}</div>
    </article>
</x-layouts::public>
