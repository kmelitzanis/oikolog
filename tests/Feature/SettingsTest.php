<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_upload_and_locale_update()
    {
        Storage::fake('public');
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 600, 600);

        $resp = $this->post(route('settings.update'), [
            'name' => 'New Name',
            'email' => $user->email,
            'currency_code' => 'USD',
            'avatar' => $file,
            'locale' => 'el',
        ]);

        $resp->assertRedirect();
        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('el', $user->locale);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($user->avatar_url, PHP_URL_PATH)));
    }
}

