<?php

namespace App\Providers;

use Illuminate\Support\Facades\App as LaravelApp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        // Prefer an explicitly set session locale (immediate user choice)
        $locale = session('locale');
        if (empty($locale)) {
            $locale = 'en';
            if (Auth::check() && Auth::user()->locale) {
                $locale = Auth::user()->locale;
            }
        }
        LaravelApp::setLocale($locale);

        // Share available locales (discover from resources/lang)
        try {
            $langPath = resource_path('lang');
            $locales = [];
            if (is_dir($langPath)) {
                foreach (scandir($langPath) as $entry) {
                    if (in_array($entry, ['.', '..'])) continue;
                    $full = $langPath . DIRECTORY_SEPARATOR . $entry;
                    // json files (en.json) or directories (en/)
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

        // Use database-backed translation loader that falls back to file loader
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('translations')) {
                $fileLoader = $this->app['translation.loader'];
                $dbLoader = new \App\Translation\DatabaseLoader($fileLoader);
                $this->app->instance('translation.loader', $dbLoader);
            }
        } catch (\Throwable $e) {
            // If the DB isn't ready (migrations running), skip binding DB loader
        }
    }
}
