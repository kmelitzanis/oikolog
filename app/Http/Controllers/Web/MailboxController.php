<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Services\InvoiceMailScanner;
use App\Services\MicrosoftMailAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MailboxController extends Controller
{
    /**
     * Connect a Gmail mailbox with an app password. Only the address and the
     * password are asked for; the server details are Gmail's and fixed.
     * Testing the login before saving means a wrong password is reported
     * here rather than at the first scheduled scan.
     */
    public function update(Request $request, InvoiceMailScanner $scanner)
    {
        $data = $request->validate([
            'username' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:190'],
        ]);

        $mailbox = Mailbox::firstOrNew(['user_id' => $request->user()->id]);
        $mailbox->fill([
            'user_id'             => $request->user()->id,
            'auth_type'           => 'password',
            'host'                => 'imap.gmail.com',
            'port'                => 993,
            'encryption'          => 'ssl',
            'username'            => $data['username'],
            'folder'              => 'INBOX',
            'is_active'           => true,
            'oauth_refresh_token' => null,
            'oauth_access_token'  => null,
            'oauth_expires_at'    => null,
            'last_error'          => null,
        ]);
        // App passwords are shown with spaces ("abcd efgh ijkl mnop").
        $mailbox->password = str_replace(' ', '', $data['password']);

        try {
            $scanner->openFolder($mailbox);
        } catch (\Throwable $e) {
            return back()->withInput($request->only('username'))->withErrors([
                'password' => InvoiceMailScanner::explainError($e->getMessage()),
            ]);
        }

        $mailbox->save();

        return back()->with('success', __('messages.mailbox_saved'));
    }

    /**
     * Send the browser to Microsoft's sign-in page. State and the PKCE
     * verifier stay in the session so the callback can prove the response
     * belongs to this request.
     */
    public function microsoftConnect(Request $request, MicrosoftMailAuth $microsoft)
    {
        abort_unless($microsoft->isConfigured(), 404);

        $state    = Str::random(40);
        $verifier = MicrosoftMailAuth::newVerifier();
        $request->session()->put('microsoft_oauth', compact('state', 'verifier'));

        return redirect()->away($microsoft->authorizeUrl($state, $verifier));
    }

    public function microsoftCallback(Request $request, MicrosoftMailAuth $microsoft)
    {
        $expected = $request->session()->pull('microsoft_oauth');

        // Declined on Microsoft's page, or a response we did not ask for.
        if ($request->filled('error')) {
            return redirect()->route('settings')->withErrors([
                'mailbox' => __('messages.mailbox_microsoft_cancelled'),
            ]);
        }
        if (! $expected || ! hash_equals($expected['state'], (string) $request->input('state'))
            || blank($request->input('code'))) {
            return redirect()->route('settings')->withErrors([
                'mailbox' => __('messages.mailbox_microsoft_failed'),
            ]);
        }

        try {
            $tokens = $microsoft->exchangeCode((string) $request->input('code'), $expected['verifier']);
        } catch (\Throwable $e) {
            Log::warning('Microsoft mailbox connect failed: ' . $e->getMessage());

            return redirect()->route('settings')->withErrors([
                'mailbox' => __('messages.mailbox_microsoft_failed'),
            ]);
        }

        $mailbox = Mailbox::firstOrNew(['user_id' => $request->user()->id]);
        $mailbox->fill([
            'user_id'             => $request->user()->id,
            'auth_type'           => 'microsoft',
            'host'                => MicrosoftMailAuth::IMAP_HOST,
            'port'                => 993,
            'encryption'          => 'ssl',
            'username'            => $tokens['email'],
            'folder'              => $mailbox->folder ?: 'INBOX',
            'is_active'           => true,
            'oauth_refresh_token' => $tokens['refresh_token'],
            'oauth_access_token'  => $tokens['access_token'],
            'oauth_expires_at'    => $tokens['expires_at'],
            'last_error'          => null,
        ]);
        $mailbox->password = null;
        $mailbox->save();

        return redirect()->route('settings')->with('success', __('messages.mailbox_microsoft_connected', [
            'email' => $tokens['email'],
        ]));
    }

    /**
     * Forget the mailbox, whichever way it was connected. For Microsoft this
     * drops the tokens held here; access can also be revoked on Microsoft's
     * side (account → privacy → apps and services).
     */
    public function disconnect(Request $request)
    {
        Mailbox::where('user_id', $request->user()->id)->delete();

        return redirect()->route('settings')->with('success', __('messages.mailbox_disconnected'));
    }

    /** Connect once and report back, rather than leaving the user guessing. */
    public function test(Request $request, InvoiceMailScanner $scanner)
    {
        $mailbox = Mailbox::where('user_id', $request->user()->id)->first();

        if (! $mailbox) {
            return back()->withErrors(['mailbox' => __('messages.mailbox_missing')]);
        }

        try {
            $scanner->openFolder($mailbox);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'mailbox' => InvoiceMailScanner::explainError($e->getMessage()),
            ]);
        }

        return back()->with('success', __('messages.mailbox_ok'));
    }

    /** Run the crawler now, so the user can see it work. */
    public function scan(Request $request, InvoiceMailScanner $scanner)
    {
        $mailbox = Mailbox::with('user')->where('user_id', $request->user()->id)->first();

        if (! $mailbox) {
            return back()->withErrors(['mailbox' => __('messages.mailbox_missing')]);
        }

        $r = $scanner->scan($mailbox);

        if ($r['error']) {
            return back()->withErrors(['mailbox' => $r['error']]);
        }

        return back()->with('success', __('messages.mailbox_scanned', [
            'scanned' => $r['scanned'],
            'created' => $r['created'],
        ]));
    }
}
