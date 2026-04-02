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
        // Priority: session > authenticated user's saved locale > app default
        $locale = session('locale')
            ?? (Auth::check() ? Auth::user()->locale : null)
            ?? config('app.locale', 'en');
        $available = ['en', 'el'];
        if (!in_array($locale, $available)) {
            $locale = 'en';
        }
        App::setLocale($locale);
        return $next($request);
    }
}
