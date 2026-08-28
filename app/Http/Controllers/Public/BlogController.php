<?php

namespace App\Http\Controllers\Public;

use App\Models\Blog;
use Illuminate\View\View;

/**
 * Public blog index + post. Ported from Admin\BlogController::publicIndex/show
 * (published-only, slug route key).
 */
class BlogController
{
    public function index(): View
    {
        return view('public.blog.index', [
            'blogs' => Blog::query()->published()->latest('published_at')->latest('id')->paginate(12),
        ]);
    }

    public function show(Blog $blog): View
    {
        abort_unless($blog->is_published, 404);

        return view('public.blog.show', ['blog' => $blog]);
    }
}
