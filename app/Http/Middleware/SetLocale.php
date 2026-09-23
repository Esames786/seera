<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Preserve legacy user preferences; locale selection never changes stored business values.
        $preference = $request->user()?->language ?? $request->session()->get('locale', 'en');
        app()->setLocale(match ($preference) {
            'ar', 'Arabic' => 'ar',
            default => 'en',
        });

        return $next($request);
    }
}
