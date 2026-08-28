<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillAmountSuggestion;
use Illuminate\Http\Request;

/**
 * Accepting a suggestion is the only path from a parsed email to a bill's
 * amount. Nothing in the crawler writes to a bill on its own.
 */
class BillAmountSuggestionController extends Controller
{
    public function accept(Request $request, Bill $bill, BillAmountSuggestion $suggestion)
    {
        $this->authorize($request, $bill, $suggestion);

        $bill->update(['current_amount' => $suggestion->amount]);

        $suggestion->update([
            'status'      => 'accepted',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        // Anything else still pending for this bill is stale now — a newer
        // invoice email supersedes an older one.
        BillAmountSuggestion::where('bill_id', $bill->id)
            ->whereKeyNot($suggestion->id)
            ->pending()
            ->update(['status' => 'rejected', 'resolved_at' => now()]);

        return back()->with('success', __('messages.suggestion_accepted'));
    }

    public function reject(Request $request, Bill $bill, BillAmountSuggestion $suggestion)
    {
        $this->authorize($request, $bill, $suggestion);

        $suggestion->update([
            'status'      => 'rejected',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back()->with('success', __('messages.suggestion_rejected'));
    }

    private function authorize(Request $request, Bill $bill, BillAmountSuggestion $suggestion): void
    {
        abort_unless($suggestion->bill_id === $bill->id, 404);

        $user = $request->user();
        $ok = $bill->created_by === $user->id
            || ($bill->is_shared && $bill->family_id && $bill->family_id === $user->family_id);

        abort_unless($ok, 403, 'You cannot act on this bill.');
        abort_unless($suggestion->status === 'pending', 409, 'Already resolved.');
    }
}
