<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = ['en', 'el'];

        // Priority: session > authenticated user's saved locale > what the
        // browser asks for > app default. The browser's preference is what
        // lets a Greek phone see a Greek sign-in page before anyone has
        // chosen anything.
        $locale = session('locale')
            ?? (Auth::check() ? Auth::user()->locale : null)
            ?? ($request->headers->has('Accept-Language') ? $request->getPreferredLanguage($available) : null)
            ?? config('app.locale', 'en');
        if (!in_array($locale, $available)) {
            $locale = 'en';
        }
        App::setLocale($locale);
        return $next($request);
    }
}
