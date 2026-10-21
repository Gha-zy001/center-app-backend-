<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED = ['en', 'ar'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = config('app.locale');

        if ($request->headers->has('Accept-Language')) {
            // Resolves values such as "ar", "en-US,en;q=0.9" or "ar-SA,ar;q=0.8" to a supported locale.
            $locale = $request->getPreferredLanguage(self::SUPPORTED) ?? $locale;
        }

        App::setLocale($locale);

        return $next($request);
    }
}
