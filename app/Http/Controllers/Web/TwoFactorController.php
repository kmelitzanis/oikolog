<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // Show 2FA challenge page (during login)
    public function challenge()
    {
        if (!session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    // Verify TOTP code during login
    public function verifyChallenge(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $userId = session('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::findOrFail($userId);
        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            str_replace(' ', '', $request->code)
        );

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid authentication code. Please try again.']);
        }

        session()->forget('2fa_user_id');
        $remember = session()->pull('2fa_remember', false);

        if ($remember) {
            $desired = config('session.remember_lifetime', 60 * 24 * 30);
            $max = config('session.max_lifetime', 60 * 24 * 30);
            $use = min($desired, $max);
            config(['session.lifetime' => $use]);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    // Show setup QR code page
    public function setup()
    {
        $user = Auth::user();

        if (!$user->two_factor_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->update(['two_factor_secret' => $secret]);
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return view('auth.two-factor-setup', [
            'qrCodeSvg' => $qrCodeSvg,
            'secret' => $user->two_factor_secret,
            'enabled' => $user->two_factor_enabled,
        ]);
    }

    // Enable 2FA after confirming code
    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = Auth::user();
        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            str_replace(' ', '', $request->code)
        );

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $user->update(['two_factor_enabled' => true]);

        return redirect()->route('2fa.setup')->with('success', 'Two-factor authentication has been enabled.');
    }

    // Disable 2FA
    public function disable(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = Auth::user();
        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            str_replace(' ', '', $request->code)
        );

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ]);

        return redirect()->route('2fa.setup')->with('success', 'Two-factor authentication has been disabled.');
    }
}
