<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the Real Estate sub-product — the visitor must be signed in on
 * the `agent` guard (an `agents` row). Legacy `agent.auth`.
 */
class EnsureAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('agent')->check()) {
            return redirect()->route('agent.login');
        }

        return $next($request);
    }
}
