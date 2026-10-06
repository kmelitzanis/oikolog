<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share available locales discovered from resources/lang (json files + subdirs)
        try {
            $langPath = resource_path('lang');
            $locales = [];
            if (is_dir($langPath)) {
                foreach (scandir($langPath) as $entry) {
                    if (in_array($entry, ['.', '..'])) continue;
                    $full = $langPath . DIRECTORY_SEPARATOR . $entry;
                    if (is_file($full) && pathinfo($full, PATHINFO_EXTENSION) === 'json') {
                        $locales[] = pathinfo($full, PATHINFO_FILENAME);
                    } elseif (is_dir($full)) {
                        $locales[] = $entry;
                    }
                }
                $locales = array_values(array_unique($locales));
            }
            View::share('availableLocales', $locales);
        } catch (\Throwable $e) {
            View::share('availableLocales', ['en']);
        }

        // Database-backed translation loader (falls back to file loader)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('translations')) {
                $fileLoader = $this->app['translation.loader'];
                $dbLoader = new \App\Translation\DatabaseLoader($fileLoader);
                $this->app->instance('translation.loader', $dbLoader);
            }
        } catch (\Throwable $e) {
            // Skip if DB isn't ready (e.g. during migrations)
        }

        $this->configureRateLimiting();

        // "Remember me" duration. Laravel's SessionGuard hard-codes this, so it
        // is set here to make it configurable per deployment; the guard is
        // resolved lazily so nothing is booted before it is needed.
        $this->app->resolving('auth', function ($auth) {
            $auth->guard('web')->setRememberDuration(
                (int) config('session.remember_lifetime', 60 * 24 * 400)
            );
        });

        // The sidebar badge needs the overdue count on every page, so it is
        // composed into the layout rather than threaded through every
        // controller. Guarded: the layout also renders before migrations run.
        View::composer('layouts.app', function ($view) {
            try {
                $view->with('overdueBillCount', \App\Models\Bill::overdueCountFor(\Illuminate\Support\Facades\Auth::user()));
            } catch (\Throwable $e) {
                $view->with('overdueBillCount', 0);
            }
        });
    }

    /**
     * Brute-force brakes for the doors that take a secret.
     *
     * Each limit is keyed on what is being guessed (the account, the pending
     * 2FA user) as well as on the address, because proxies are trusted and an
     * address alone is cheap to rotate. Distinct key prefixes keep the two
     * limits from sharing one counter.
     */
    private function configureRateLimiting(): void
    {
        // A form gets its error back beside the field instead of a bare 429
        // page; API callers get the usual JSON with Retry-After.
        $tooMany = fn (string $field) => function (Request $request, array $headers) use ($field) {
            $message = __('messages.too_many_attempts', ['seconds' => $headers['Retry-After'] ?? 60]);

            return $request->expectsJson()
                ? response()->json(['message' => $message], 429, $headers)
                : back()->withErrors([$field => $message])->onlyInput('email');
        };

        RateLimiter::for('login', function (Request $request) use ($tooMany) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login-account:' . $email)->response($tooMany('email')),
                Limit::perMinute(20)->by('login-ip:' . $request->ip())->response($tooMany('email')),
            ];
        });

        // Six digits are a small space: the hourly cap is what keeps a patient
        // guesser from walking it.
        RateLimiter::for('two-factor', function (Request $request) use ($tooMany) {
            $who = $request->user()?->getAuthIdentifier()
                ?? ($request->hasSession() ? $request->session()->get('2fa_user_id') : null)
                ?? $request->ip();

            return [
                Limit::perMinute(5)->by('2fa-minute:' . $who)->response($tooMany('code')),
                Limit::perHour(20)->by('2fa-hour:' . $who)->response($tooMany('code')),
            ];
        });

        // Joining a family by code is guessing a code.
        RateLimiter::for('family-join', function (Request $request) use ($tooMany) {
            return Limit::perMinute(5)
                ->by('family-join:' . ($request->user()?->getAuthIdentifier() ?? $request->ip()))
                ->response($tooMany('invite_code'));
        });
    }
}
