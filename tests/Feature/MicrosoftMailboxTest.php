<?php

namespace Tests\Feature;

use App\Models\Mailbox;
use App\Models\User;
use App\Services\MicrosoftMailAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Connecting an Outlook/Hotmail mailbox by signing in with Microsoft: the
 * redirect carries state + PKCE, the callback refuses anything it did not
 * ask for, tokens are stored encrypted, and expiring tokens are refreshed.
 */
class MicrosoftMailboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.microsoft.client_id' => 'client-123',
            'services.microsoft.client_secret' => 'secret-xyz',
            'services.microsoft.tenant' => 'common',
        ]);
    }

    private function idToken(string $email): string
    {
        $part = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');

        return $part(['alg' => 'none']) . '.' . $part(['email' => $email]) . '.sig';
    }

    private function fakeTokenEndpoint(array $body, int $status = 200): void
    {
        Http::fake(['login.microsoftonline.com/*' => Http::response($body, $status)]);
    }

    public function test_connect_redirects_to_microsoft_with_state_and_pkce(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('mailbox.microsoft.connect'));

        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('client-123', $q['client_id']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertStringContainsString('IMAP.AccessAsUser.All', $q['scope']);
        $this->assertStringContainsString('offline_access', $q['scope']);
        $this->assertSame(session('microsoft_oauth.state'), $q['state']);
    }

    public function test_the_callback_stores_an_encrypted_microsoft_mailbox(): void
    {
        $user = User::factory()->create();
        $this->fakeTokenEndpoint([
            'access_token' => 'at-1', 'refresh_token' => 'rt-1', 'expires_in' => 3600,
            'id_token' => $this->idToken('me@outlook.com'),
        ]);

        $this->actingAs($user)
            ->withSession(['microsoft_oauth' => ['state' => 'st', 'verifier' => 'ver']])
            ->get(route('mailbox.microsoft.callback', ['code' => 'c0de', 'state' => 'st']))
            ->assertRedirect(route('settings'))
            ->assertSessionHasNoErrors();

        $mailbox = Mailbox::firstWhere('user_id', $user->id);
        $this->assertTrue($mailbox->usesMicrosoft());
        $this->assertSame('me@outlook.com', $mailbox->username);
        $this->assertSame('rt-1', $mailbox->oauth_refresh_token);
        $this->assertNull($mailbox->password);

        // Encrypted at rest, not stored as the plain token.
        $raw = \DB::table('mailboxes')->where('id', $mailbox->id)->value('oauth_refresh_token');
        $this->assertNotSame('rt-1', $raw);

        Http::assertSent(fn ($r) => $r['code_verifier'] === 'ver' && $r['grant_type'] === 'authorization_code');
    }

    public function test_the_callback_refuses_a_state_it_did_not_issue(): void
    {
        $user = User::factory()->create();
        Http::fake();

        $this->actingAs($user)
            ->withSession(['microsoft_oauth' => ['state' => 'st', 'verifier' => 'ver']])
            ->get(route('mailbox.microsoft.callback', ['code' => 'c0de', 'state' => 'forged']))
            ->assertRedirect(route('settings'))
            ->assertSessionHasErrors('mailbox');

        Http::assertNothingSent();
        $this->assertNull(Mailbox::firstWhere('user_id', $user->id));
    }

    public function test_an_expiring_token_is_refreshed_and_the_rotated_one_kept(): void
    {
        $user = User::factory()->create();
        $mailbox = Mailbox::create([
            'user_id' => $user->id, 'auth_type' => 'microsoft', 'host' => 'outlook.office365.com',
            'port' => 993, 'encryption' => 'ssl', 'username' => 'me@outlook.com', 'folder' => 'INBOX',
            'oauth_refresh_token' => 'rt-old', 'oauth_access_token' => 'at-old',
            'oauth_expires_at' => now()->addMinute(),
        ]);
        $this->fakeTokenEndpoint(['access_token' => 'at-new', 'refresh_token' => 'rt-new', 'expires_in' => 3600]);

        $token = app(MicrosoftMailAuth::class)->accessToken($mailbox);

        $this->assertSame('at-new', $token);
        $this->assertSame('rt-new', $mailbox->fresh()->oauth_refresh_token);
        Http::assertSent(fn ($r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'rt-old');
    }

    public function test_a_fresh_token_is_reused_without_calling_microsoft(): void
    {
        $user = User::factory()->create();
        $mailbox = Mailbox::create([
            'user_id' => $user->id, 'auth_type' => 'microsoft', 'host' => 'outlook.office365.com',
            'port' => 993, 'encryption' => 'ssl', 'username' => 'me@outlook.com', 'folder' => 'INBOX',
            'oauth_refresh_token' => 'rt', 'oauth_access_token' => 'at-current',
            'oauth_expires_at' => now()->addHour(),
        ]);
        Http::fake();

        $this->assertSame('at-current', app(MicrosoftMailAuth::class)->accessToken($mailbox));
        Http::assertNothingSent();
    }

    public function test_a_revoked_grant_asks_the_user_to_reconnect(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $mailbox = Mailbox::create([
            'user_id' => $user->id, 'auth_type' => 'microsoft', 'host' => 'outlook.office365.com',
            'port' => 993, 'encryption' => 'ssl', 'username' => 'me@outlook.com', 'folder' => 'INBOX',
            'oauth_refresh_token' => 'rt', 'oauth_expires_at' => now()->subMinute(),
        ]);
        $this->fakeTokenEndpoint(['error' => 'invalid_grant'], 400);

        $this->expectExceptionMessage('connect it again');
        app(MicrosoftMailAuth::class)->accessToken($mailbox);
    }

    public function test_disconnect_removes_the_connection(): void
    {
        $user = User::factory()->create();
        Mailbox::create([
            'user_id' => $user->id, 'auth_type' => 'microsoft', 'host' => 'outlook.office365.com',
            'port' => 993, 'encryption' => 'ssl', 'username' => 'me@outlook.com', 'folder' => 'INBOX',
            'oauth_refresh_token' => 'rt',
        ]);

        $this->actingAs($user)->post(route('mailbox.disconnect'))->assertRedirect(route('settings'));

        $this->assertNull(Mailbox::firstWhere('user_id', $user->id));
    }

    public function test_settings_shows_the_button_only_when_configured(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get(route('settings'))->assertOk()
            ->assertSee(route('mailbox.microsoft.connect'), false);

        config(['services.microsoft.client_id' => null]);
        $this->actingAs($user)->get(route('settings'))->assertOk()
            ->assertDontSee(route('mailbox.microsoft.connect'), false);
    }
}
