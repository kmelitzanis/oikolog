<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Services\InvoiceMailScanner;
use Illuminate\Http\Request;

class MailboxController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'host'       => ['required', 'string', 'max:190'],
            'port'       => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'in:ssl,tls,none'],
            'username'   => ['required', 'string', 'max:190'],
            // Left blank on edit means "keep the stored one".
            'password'   => ['nullable', 'string', 'max:190'],
            'folder'     => ['required', 'string', 'max:190'],
            'is_active'  => ['nullable'],
        ]);

        $mailbox = Mailbox::firstOrNew(['user_id' => $request->user()->id]);

        // A blank password field means "keep the stored one" — the form never
        // renders the existing password back to the browser.
        $password = $data['password'] ?? null;
        unset($data['password']);

        $mailbox->fill([
            ...$data,
            'user_id'   => $request->user()->id,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if (filled($password)) {
            $mailbox->password = $password;
        }

        abort_if(blank($mailbox->password), 422, 'A password is required the first time.');

        $mailbox->save();

        return back()->with('success', __('messages.mailbox_saved'));
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
            return back()->withErrors(['mailbox' => $e->getMessage()]);
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
