<?php

namespace App\Providers;

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
    }
}
