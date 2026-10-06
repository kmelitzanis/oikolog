<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorVerifier;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /** How long a correct password keeps the door open for the code. */
    public const PENDING_TTL_SECONDS = 300;

    public function __construct(
        private readonly TwoFactorVerifier $verifier,
        private readonly Google2FA $google2fa,
    ) {
    }

    // Show 2FA challenge page (during login)
    public function challenge(Request $request)
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    // Verify TOTP code during login
    public function verifyChallenge(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => __('messages.two_factor_session_expired')]);
        }

        if (! $this->verifier->verify($user, $request->input('code'))) {
            return back()->withErrors(['code' => __('messages.two_factor_invalid_code')]);
        }

        $request->session()->forget(['2fa_user_id', '2fa_expires_at']);
        $remember = (bool) $request->session()->pull('2fa_remember', false);

        // How long the recaller cookie lasts is set once, on the guard — see
        // AppServiceProvider. Rewriting session.lifetime here only affected the
        // single response that carried it, so it never did anything.
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    // Show setup QR code page
    public function setup()
    {
        $user = Auth::user();

        // Once 2FA is on, the shared secret is never shown again: anyone who
        // got hold of the session could otherwise copy it into their own
        // authenticator and the second factor would stop being one.
        if ($user->two_factor_enabled) {
            return view('auth.two-factor-setup', [
                'qrCodeSvg' => null,
                'secret'    => null,
                'enabled'   => true,
            ]);
        }

        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => $this->google2fa->generateSecretKey()])->save();
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));

        return view('auth.two-factor-setup', [
            'qrCodeSvg' => $writer->writeString($qrCodeUrl),
            'secret'    => $user->two_factor_secret,
            'enabled'   => false,
        ]);
    }

    // Enable 2FA after confirming code
    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $user = $request->user();

        if ($user->two_factor_enabled) {
            return redirect()->route('2fa.setup');
        }

        if (! $this->verifier->verify($user, $request->input('code'))) {
            return back()->withErrors(['code' => __('messages.two_factor_invalid_code')]);
        }

        $user->forceFill(['two_factor_enabled' => true])->save();

        return redirect()->route('2fa.setup')->with('success', __('messages.two_factor_enabled'));
    }

    // Disable 2FA
    public function disable(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $user = $request->user();

        if (! $this->verifier->verify($user, $request->input('code'))) {
            return back()->withErrors(['code' => __('messages.two_factor_invalid_code')]);
        }

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret'  => null,
        ])->save();

        return redirect()->route('2fa.setup')->with('success', __('messages.two_factor_disabled'));
    }

    /**
     * The user who passed the password step, as long as that was recently.
     * An abandoned challenge must not stay redeemable for the whole session.
     */
    private function pendingUser(Request $request): ?User
    {
        $userId    = $request->session()->get('2fa_user_id');
        $expiresAt = (int) $request->session()->get('2fa_expires_at', 0);

        if (! $userId || $expiresAt < now()->getTimestamp()) {
            $request->session()->forget(['2fa_user_id', '2fa_expires_at', '2fa_remember']);

            return null;
        }

        $user = User::find($userId);

        return $user && $user->two_factor_enabled ? $user : null;
    }
}
