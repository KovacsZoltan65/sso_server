<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $availableLocales = config('app.available_locales', ['hu', 'en']);
        $fallbackLocale = config('app.locale');

        $sessionLocale = null;

        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
        }

        $locale = in_array($sessionLocale, $availableLocales, true)
            ? $sessionLocale
            : $fallbackLocale;

        app()->setLocale($locale);

        return $next($request);
    }
}