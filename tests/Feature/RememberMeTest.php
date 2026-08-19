<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * "Remember me" has to survive signing in somewhere else.
 *
 * Auth::logout() cycles the remember token, which invalidates the recaller
 * cookie on *every* device — so a login flow that logs a user in and straight
 * back out (the 2FA hand-off used to) quietly signed out the phone every time
 * the laptop signed in.
 */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'email'    => 'kostas@example.com',
            'password' => Hash::make('secret-password'),
        ], $attrs));
    }

    private function login(array $extra = [])
    {
        return $this->post(route('login.post'), array_merge([
            'email'    => 'kostas@example.com',
            'password' => 'secret-password',
            'remember' => '1',
        ], $extra));
    }

    public function test_remember_me_issues_a_recaller_cookie(): void
    {
        $user = $this->user();

        $this->login()->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->fresh()->remember_token);
    }

    public function test_the_recaller_outlives_a_month(): void
    {
        $this->user();

        $response = $this->login();
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($cookie, 'No remember cookie was issued.');
        $this->assertGreaterThan(
            now()->addDays(31)->getTimestamp(),
            $cookie->getExpiresTime(),
            'The remember cookie expires within a month.',
        );
    }

    /** The duration is a deployment knob, not Laravel's hard-coded default. */
    public function test_the_recaller_honours_the_configured_lifetime(): void
    {
        config(['session.remember_lifetime' => 60 * 24 * 45]);
        $this->user();

        $cookie = collect($this->login()->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertEqualsWithDelta(
            now()->addDays(45)->getTimestamp(),
            $cookie->getExpiresTime(),
            60,
        );
    }

    public function test_signing_in_elsewhere_does_not_invalidate_other_devices(): void
    {
        $user = $this->user();

        $this->login()->assertRedirect();
        $first = $user->fresh()->remember_token;
        $this->assertNotEmpty($first);

        // A second, independent sign-in — the other device's cookie must survive.
        $this->flushSession();
        $this->login()->assertRedirect();

        $this->assertSame($first, $user->fresh()->remember_token);
    }

    public function test_two_factor_login_also_leaves_other_devices_signed_in(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = $this->user([
            'two_factor_enabled' => true,
            'two_factor_secret'  => $secret,
        ]);
        $user->forceFill(['remember_token' => 'existing-token-from-the-phone'])->save();

        $this->login()->assertRedirect(route('2fa.challenge'));

        // The hand-off to the challenge must not touch the stored token.
        $this->assertSame('existing-token-from-the-phone', $user->fresh()->remember_token);

        $this->post(route('2fa.verify'), ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_bad_credentials_are_rejected(): void
    {
        $this->user();

        $this->login(['password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_two_factor_users_are_not_logged_in_before_the_code(): void
    {
        $user = $this->user([
            'two_factor_enabled' => true,
            'two_factor_secret'  => (new Google2FA())->generateSecretKey(),
        ]);

        $this->login()->assertRedirect(route('2fa.challenge'));

        $this->assertGuest();
    }
}
