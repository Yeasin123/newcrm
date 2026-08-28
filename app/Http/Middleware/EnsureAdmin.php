<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the admin panel: authenticated AND `type = 1` on the legacy schema.
 * Mirrors the old app's `Admin` middleware.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin() || $user->login_off) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
