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

        // Insert DB override for 'dashboard'
        Translation::create(['locale' => 'en', 'group' => 'messages', 'key' => 'dashboard', 'value' => 'DB Dashboard']);

        // Clear translator cache and fetch
        app('translator')->load('en', 'messages');

        $val = __('messages.dashboard');
        $this->assertEquals('DB Dashboard', $val);
    }
}

