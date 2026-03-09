<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $langPath = resource_path('lang');
        $locales = array_values(array_filter(File::directories($langPath), function ($d) {
            return is_dir($d);
        }));
        $locales = array_map(fn($d) => basename($d), $locales);

        foreach ($locales as $locale) {
            $file = resource_path('lang/' . $locale . '/messages.php');
            if (!file_exists($file)) continue;
            $arr = include $file;
            foreach ($arr as $key => $value) {
                Translation::updateOrCreate([
                    'locale' => $locale,
                    'group' => 'messages',
                    'key' => $key,
                ], ['value' => $value]);
            }
        }
    }
}

