<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FamilyController extends Controller
{
    public function index(Request $request)
    {
        $user     = $request->user();
        $family   = null;
        $activity = collect();
        $sharedBills = collect();

        if ($user->family_id) {
            $family = $user->family()->with('members')->first();

            $sharedBills = \App\Models\Bill::forUser($user)
                ->where('is_shared', true)
                ->orderByDesc('amount')
                ->get(['id', 'name', 'amount', 'currency_code']);

            // The mockup shows a "recent activity" feed. There is no activity
            // log in the schema, so this is assembled from the two events the
            // app actually records: payments made, and bills added.
            $familyBillIds = \App\Models\Bill::forUser($user)->pluck('id');

            $payments = \App\Models\Payment::whereIn('bill_id', $familyBillIds)
                ->with(['bill:id,name', 'paidBy:id,name'])
                ->latest('paid_at')
                ->take(6)
                ->get()
                ->map(fn($p) => [
                    'type'  => 'paid',
                    'actor' => $p->paidBy?->name,
                    'subject' => $p->bill?->name,
                    'at'    => $p->paid_at,
                ]);

            $addedBills = \App\Models\Bill::forUser($user)
                ->with('creator:id,name')
                ->latest('created_at')
                ->take(6)
                ->get()
                ->map(fn($b) => [
                    'type'  => 'added',
                    'actor' => $b->creator?->name,
                    'subject' => $b->name,
                    'at'    => $b->created_at,
                ]);

            $activity = $payments->concat($addedBills)
                ->filter(fn($a) => $a['at'] !== null)
                ->sortByDesc('at')
                ->take(6)
                ->values();
        }

        return view('family.index', compact('family', 'activity', 'sharedBills'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_if($user->family_id, 422, 'You already belong to a family.');

        $data   = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $family = Family::create([
            'name'     => $data['name'],
            'owner_id' => $user->id,
        ]);
        $user->update(['family_id' => $family->id, 'family_role' => 'owner']);

        return redirect()->route('family.index')->with('success', 'Family group created!');
    }

    public function join(Request $request)
    {
        $user = $request->user();
        abort_if($user->family_id, 422, 'You already belong to a family.');

        $data   = $request->validate(['invite_code' => ['required', 'string']]);
        $family = Family::where('invite_code', strtoupper($data['invite_code']))->first();
        abort_unless($family, 404, 'Invalid invite code.');

        $user->update(['family_id' => $family->id, 'family_role' => 'member']);

        return redirect()->route('family.index')->with('success', 'Joined ' . $family->name . '!');
    }

    public function leave(Request $request)
    {
        $user = $request->user();
        abort_unless($user->family_id, 422, 'Not in a family.');

        if ($user->isFamilyOwner() && $user->family->members()->count() > 1) {
            return back()->withErrors(['family' => 'Transfer ownership before leaving.']);
        }
        if ($user->isFamilyOwner()) {
            $user->family->delete();
        }
        $user->update(['family_id' => null, 'family_role' => null]);

        return redirect()->route('family.index')->with('success', 'You left the family group.');
    }

    public function regenerateCode(Request $request)
    {
        abort_unless($request->user()->isFamilyAdmin(), 403, 'Admins only.');
        $request->user()->family->regenerateInviteCode();

        return back()->with('success', 'Invite code regenerated.');
    }

    public function removeMember(Request $request, User $member)
    {
        $user = $request->user();
        // Only family owner (creator) may remove members
        abort_unless($user->isFamilyOwner(), 403, 'Only family owner may remove members.');
        abort_unless($member->family_id === $user->family_id, 422, 'Not in your family.');
        abort_if($member->id === $user->id, 422, 'Cannot remove yourself.');

        $member->update(['family_id' => null, 'family_role' => null]);

        return back()->with('success', $member->name . ' removed from family.');
    }

    public function transferOwnership(Request $request, User $member)
    {
        $user = $request->user();
        abort_unless($user->isFamilyOwner(), 403, 'Only family owner may transfer ownership.');
        abort_unless($member->family_id === $user->family_id, 422, 'Not in your family.');
        abort_if($member->id === $user->id, 422, 'Cannot transfer to yourself.');

        // Demote current owner to admin
        $user->update(['family_role' => 'admin']);
        // Promote new owner
        $member->update(['family_role' => 'owner']);
        // Update family owner_id
        $user->family->update(['owner_id' => $member->id]);

        return back()->with('success', 'Ownership transferred to ' . $member->name);
    }
}
