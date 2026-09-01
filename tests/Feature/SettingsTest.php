<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mailbox_form_renders_normally(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings'))
            ->assertOk()
            ->assertSee(__('messages.imap_host'))
            ->assertDontSee(__('messages.mailbox_needs_migration'));
    }

    /**
     * The scan button is disabled until a mailbox is stored.
     *
     * It is asserted through a rendered request because the bug this replaces
     * was a *compile* failure: a Blade directive inside a `<x-btn>` tag stopped
     * the tag being compiled as a component and unbalanced the whole file, so
     * the page 500'd in production while every existing test still passed.
     */
    public function test_the_scan_button_follows_whether_a_mailbox_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings'))->assertOk()
            ->assertSee('form="mailbox-scan-form" disabled="disabled"', false);

        Mailbox::create([
            'user_id'  => $user->id,
            'host'     => 'imap.example.com',
            'username' => 'someone@example.com',
            'password' => 'secret',
            'folder'   => 'INBOX',
        ]);

        $this->actingAs($user)->get(route('settings'))->assertOk()
            ->assertDontSee('form="mailbox-scan-form" disabled', false);
    }

    /**
     * A deployment where migrations have not run must still let people reach
     * their profile and password. The invoice-mail card is optional; it used to
     * take the whole page down with it.
     *
     * Asserting that the form is *absent* here is the point: an unclosed
     * directive around it renders both branches, which is exactly the bug this
     * guards — the page still returned 200 while shipping broken Blade.
     */
    public function test_settings_survives_a_missing_mailbox_table(): void
    {
        Schema::drop('mailboxes');

        $this->actingAs(User::factory()->create())
            ->get(route('settings'))
            ->assertOk()
            ->assertSee(__('messages.new_password'))
            ->assertSee(__('messages.mailbox_needs_migration'))
            ->assertDontSee(__('messages.imap_host'));
    }

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

