<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate. Reproduces the legacy `CheckPermission`
 * behavior exactly:
 *
 *   - superadmin (`role_id IS NULL`) is always allowed;
 *   - a user with no role relation falls through to allowed (legacy quirk —
 *     logged here so it can be tightened deliberately later);
 *   - otherwise the route is allowed if the role's `permissions` array
 *     contains ANY of the strings passed to the middleware.
 *
 * Usage: ->middleware('permission:Customers') or 'permission:Customers,Sale Sheet'
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $role = $user->role;

        if (! $role) {
            Log::warning('CheckPermission: user has no role relation, allowing by legacy fall-through', [
                'user_id' => $user->id,
                'route' => $request->path(),
            ]);

            return $next($request);
        }

        $granted = $role->permissions ?? [];

        foreach ($permissions as $permission) {
            if (in_array($permission, $granted, true)) {
                return $next($request);
            }
        }

        abort(403, 'Permission denied.');
    }
}
