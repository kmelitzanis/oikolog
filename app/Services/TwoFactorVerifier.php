<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

/**
 * Checks a TOTP code for a user, and refuses to accept the same code twice.
 *
 * A six-digit code stays valid for its whole 30-second step (plus the window
 * either side), so a code read over someone's shoulder or lifted from a proxy
 * log could otherwise be replayed straight away. The last accepted step is
 * remembered per user and only newer ones pass.
 */
class TwoFactorVerifier
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly Google2FA $google2fa = new Google2FA())
    {
    }

    public function verify(User $user, ?string $code): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code);

        if (! $user->two_factor_secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $cacheKey = 'two-factor:last-step:' . $user->getKey();

        // Passing an old step (0 when none) makes the library return the
        // matching step rather than a bare `true`, so it can be remembered.
        $step = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            $code,
            (int) Cache::get($cacheKey, 0),
        );

        if ($step === false) {
            return false;
        }

        Cache::put($cacheKey, (int) $step, self::CACHE_TTL_SECONDS);

        return true;
    }
}
