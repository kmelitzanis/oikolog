<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'email'    => 'kostas@example.com',
            'password' => Hash::make('secret-password'),
        ], $attrs));
    }

    private function twoFactorUser(): array
    {
        $secret = (new Google2FA())->generateSecretKey();

        return [$this->user(['two_factor_enabled' => true, 'two_factor_secret' => $secret]), $secret];
    }

    public function test_repeated_wrong_passwords_lock_the_account_for_a_while(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['email' => 'kostas@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // Even the right password is turned away until the window passes.
        $this->post(route('login.post'), ['email' => 'kostas@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->post(route('login.post'), ['email' => 'kostas@example.com', 'password' => 'secret-password'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_the_api_refuses_a_token_without_the_second_factor(): void
    {
        [, $secret] = $this->twoFactorUser();

        $this->postJson('/api/auth/login', ['email' => 'kostas@example.com', 'password' => 'secret-password'])
            ->assertStatus(422)
            ->assertJson(['two_factor_required' => true])
            ->assertJsonMissing(['token']);

        $this->postJson('/api/auth/login', [
            'email' => 'kostas@example.com', 'password' => 'secret-password', 'code' => '000000',
        ])->assertStatus(401);

        $this->postJson('/api/auth/login', [
            'email' => 'kostas@example.com', 'password' => 'secret-password',
            'code'  => (new Google2FA())->getCurrentOtp($secret),
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_two_factor_code_cannot_be_used_twice(): void
    {
        [$user, $secret] = $this->twoFactorUser();
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->post(route('login.post'), ['email' => 'kostas@example.com', 'password' => 'secret-password']);
        $this->post(route('2fa.verify'), ['code' => $code])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.post'), ['email' => 'kostas@example.com', 'password' => 'secret-password']);
        $this->post(route('2fa.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_an_abandoned_two_factor_challenge_expires(): void
    {
        [, $secret] = $this->twoFactorUser();

        $this->post(route('login.post'), ['email' => 'kostas@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('2fa.challenge'));

        $this->travel(6)->minutes();

        $this->get(route('2fa.challenge'))->assertRedirect(route('login'));
        $this->post(route('2fa.verify'), ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = $this->user();

        $this->actingAs($user)->withSession(['marker' => 'kept'])
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('marker');

        $this->assertGuest();
    }

    public function test_the_secret_is_not_shown_once_two_factor_is_on(): void
    {
        [$user, $secret] = $this->twoFactorUser();

        $this->actingAs($user)->get(route('2fa.setup'))
            ->assertOk()
            ->assertDontSee($secret)
            ->assertSee(__('messages.two_factor_disable'));
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $user = $this->user();
        $payload = [
            'name' => $user->name, 'email' => $user->email, 'currency_code' => 'EUR',
            'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
        ];

        $this->actingAs($user)->post(route('settings.update'), $payload)
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('secret-password', $user->fresh()->password));

        $this->actingAs($user)->post(route('settings.update'), $payload + ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post(route('settings.update'), $payload + ['current_password' => 'secret-password'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_page_renders_in_greek(): void
    {
        $this->withSession(['locale' => 'el'])->get(route('login'))
            ->assertOk()
            ->assertSee(__('messages.sign_in', [], 'el'));
    }
}
