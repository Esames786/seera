<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts issued with the shared default password cannot use the panel until
 * the holder sets their own. Everything except the change-password screen and
 * logout is redirected, so the prompt cannot simply be navigated away from.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('admin.password.change', 'admin.password.change.update', 'logout')) {
            return $next($request);
        }

        return redirect()->route('admin.password.change');
    }
}
