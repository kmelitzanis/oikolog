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

    public function test_the_mailbox_card_offers_gmail_and_no_raw_imap_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings'))
            ->assertOk()
            ->assertSee(__('messages.mailbox_gmail_connect'))
            ->assertDontSee(__('messages.imap_host'))
            ->assertDontSee(__('messages.mailbox_needs_migration'));
    }

    /**
     * Test and scan only appear once a mailbox is connected.
     *
     * Asserted through a rendered request because an earlier bug here was a
     * *compile* failure: a Blade directive inside a `<x-btn>` tag unbalanced
     * the whole file, so the page 500'd while every other test still passed.
     */
    public function test_scan_appears_once_a_mailbox_is_connected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings'))->assertOk()
            ->assertDontSee('form="mailbox-scan-form"', false);

        Mailbox::create([
            'user_id'  => $user->id,
            'host'     => 'imap.gmail.com',
            'username' => 'someone@gmail.com',
            'password' => 'secret',
            'folder'   => 'INBOX',
        ]);

        $this->actingAs($user)->get(route('settings'))->assertOk()
            ->assertSee('form="mailbox-scan-form"', false)
            ->assertSee('someone@gmail.com');
    }

    public function test_connecting_gmail_fills_in_the_server_details(): void
    {
        $user = User::factory()->create();
        $this->mock(\App\Services\InvoiceMailScanner::class)
            ->shouldReceive('openFolder')->once()->andReturn(null);

        $this->actingAs($user)->post(route('mailbox.update'), [
            'username' => 'me@gmail.com',
            'password' => 'abcd efgh ijkl mnop',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $mailbox = Mailbox::firstWhere('user_id', $user->id);
        $this->assertSame('imap.gmail.com', $mailbox->host);
        $this->assertSame(993, $mailbox->port);
        $this->assertSame('abcdefghijklmnop', $mailbox->password);
    }

    public function test_a_rejected_gmail_password_is_not_saved(): void
    {
        $user = User::factory()->create();
        $this->mock(\App\Services\InvoiceMailScanner::class)
            ->shouldReceive('openFolder')->once()->andThrow(new \RuntimeException('[AUTHENTICATIONFAILED] Invalid credentials'));

        $this->actingAs($user)->post(route('mailbox.update'), [
            'username' => 'me@gmail.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('password');

        $this->assertNull(Mailbox::firstWhere('user_id', $user->id));
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

