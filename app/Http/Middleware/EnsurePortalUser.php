<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the customer portal — the visitor must be signed in on the
 * `user` guard (a `clients` row) and their policy must not be locked
 * (`clients.login_off`).
 */
class EnsurePortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = Auth::guard('user')->user();

        if (! $client) {
            return redirect()->route('login.user');
        }

        if ((int) $client->login_off === 1) {
            Auth::guard('user')->logout();

            return redirect()->route('login.user')
                ->withErrors(['email' => __('Your policy has been canceled.')]);
        }

        return $next($request);
    }
}
