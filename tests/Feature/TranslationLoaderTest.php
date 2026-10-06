<?php

namespace Tests\Feature;

use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_translation_overrides_file()
    {
        // Ensure file has a value
        $fileVal = __('messages.dashboard');
        $this->assertIsString($fileVal);
        $this->assertNotSame('DB Dashboard', $fileVal);

        // Insert DB override for 'dashboard'
        Translation::create(['locale' => 'en', 'group' => 'messages', 'key' => 'dashboard', 'value' => 'DB Dashboard']);

        // Forget what the translator already loaded, then ask again.
        app('translator')->setLoaded([]);

        $this->assertEquals('DB Dashboard', __('messages.dashboard'));
    }
}
