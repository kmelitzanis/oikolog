<?php

namespace App\Services;

use App\Models\Mailbox;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OAuth 2.0 sign-in with Microsoft for reading a mailbox over IMAP.
 *
 * Outlook.com and Microsoft 365 refuse every password over IMAP, app
 * passwords included; XOAUTH2 with a delegated token is the only way in. The
 * user signs in on Microsoft's own page once (authorization code + PKCE), the
 * app keeps the refresh token and trades it for short-lived access tokens
 * before each connection. No password ever reaches the app.
 */
class MicrosoftMailAuth
{
    public const IMAP_HOST = 'outlook.office365.com';

    /** IMAP access, plus a refresh token and the signed-in address. */
    private const SCOPES = 'openid email offline_access https://outlook.office.com/IMAP.AccessAsUser.All';

    /** Refresh this long before the token actually expires. */
    private const EXPIRY_MARGIN_SECONDS = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'));
    }

    public function redirectUri(): string
    {
        return route('mailbox.microsoft.callback');
    }

    /**
     * Where to send the browser to sign in. The caller keeps $state and
     * $verifier in the session and checks them on the way back.
     */
    public function authorizeUrl(string $state, string $verifier): string
    {
        return $this->endpoint('authorize') . '?' . http_build_query([
            'client_id'             => config('services.microsoft.client_id'),
            'response_type'         => 'code',
            'redirect_uri'          => $this->redirectUri(),
            'response_mode'         => 'query',
            'scope'                 => self::SCOPES,
            'state'                 => $state,
            'code_challenge'        => $this->challenge($verifier),
            'code_challenge_method' => 'S256',
            // Always offer the account picker, so connecting a different
            // mailbox doesn't silently reuse the browser's signed-in account.
            'prompt'                => 'select_account',
        ]);
    }

    public static function newVerifier(): string
    {
        return Str::random(64);
    }

    /**
     * Trade the code from the callback for tokens.
     *
     * @return array{email: string, refresh_token: string, access_token: string, expires_at: Carbon}
     */
    public function exchangeCode(string $code, string $verifier): array
    {
        $tokens = $this->tokenRequest([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
            'code_verifier' => $verifier,
        ]);

        $email = $this->emailFromIdToken($tokens['id_token'] ?? '');
        if (! $email) {
            throw new RuntimeException('Microsoft did not return the account\'s email address.');
        }
        if (empty($tokens['refresh_token'])) {
            throw new RuntimeException('Microsoft did not return a refresh token.');
        }

        return [
            'email'         => $email,
            'refresh_token' => $tokens['refresh_token'],
            'access_token'  => $tokens['access_token'],
            'expires_at'    => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ];
    }

    /**
     * A usable access token for the mailbox, refreshing (and storing the
     * rotated refresh token) when the current one is about to expire.
     */
    public function accessToken(Mailbox $mailbox): string
    {
        if (filled($mailbox->oauth_access_token)
            && $mailbox->oauth_expires_at
            && $mailbox->oauth_expires_at->gt(now()->addSeconds(self::EXPIRY_MARGIN_SECONDS))) {
            return $mailbox->oauth_access_token;
        }

        if (blank($mailbox->oauth_refresh_token)) {
            throw new RuntimeException(__('messages.mailbox_microsoft_reconnect'));
        }

        $tokens = $this->tokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $mailbox->oauth_refresh_token,
        ]);

        $mailbox->forceFill([
            'oauth_access_token'  => $tokens['access_token'],
            // Microsoft rotates refresh tokens; keep the newest.
            'oauth_refresh_token' => $tokens['refresh_token'] ?? $mailbox->oauth_refresh_token,
            'oauth_expires_at'    => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ])->save();

        return $mailbox->oauth_access_token;
    }

    private function tokenRequest(array $params): array
    {
        $response = Http::asForm()->timeout(15)->post($this->endpoint('token'), $params + [
            'client_id'     => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'scope'         => self::SCOPES,
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            // invalid_grant = the user revoked access, changed password, or
            // the refresh token aged out: only signing in again fixes it.
            if ($response->json('error') === 'invalid_grant') {
                throw new RuntimeException(__('messages.mailbox_microsoft_reconnect'));
            }

            throw new RuntimeException('Microsoft: ' . ($response->json('error_description') ?? $response->json('error') ?? 'HTTP ' . $response->status()));
        }

        return $response->json();
    }

    private function endpoint(string $action): string
    {
        return 'https://login.microsoftonline.com/' . config('services.microsoft.tenant', 'common') . '/oauth2/v2.0/' . $action;
    }

    private function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * The id_token arrives straight from Microsoft's token endpoint over TLS,
     * so reading its claims without verifying the signature is safe here —
     * it is not a token presented to us by the browser.
     */
    private function emailFromIdToken(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }

        $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?: [];

        return $claims['email'] ?? $claims['preferred_username'] ?? null;
    }
}
