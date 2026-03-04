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
        $user   = $request->user();
        $family = null;

        if ($user->family_id) {
            $family = $user->family()->with('members')->first();
        }

        return view('family.index', compact('family'));
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
        abort_unless($user->isFamilyAdmin(), 403, 'Admins only.');
        abort_unless($member->family_id === $user->family_id, 422, 'Not in your family.');
        abort_if($member->id === $user->id, 422, 'Cannot remove yourself.');

        $member->update(['family_id' => null, 'family_role' => null]);

        return back()->with('success', $member->name . ' removed from family.');
    }
}

