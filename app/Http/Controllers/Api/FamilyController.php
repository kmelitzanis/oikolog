<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        abort_if($request->user()->family_id, 422, 'You already belong to a family.');
        $data   = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $family = Family::create(['name' => $data['name'], 'owner_id' => $request->user()->id]);
        $request->user()->update(['family_id' => $family->id, 'family_role' => 'owner']);
        return response()->json(['data' => $this->familyResource($family->load('members'))], 201);
    }

    public function join(Request $request): JsonResponse
    {
        abort_if($request->user()->family_id, 422, 'You already belong to a family.');
        $data   = $request->validate(['invite_code' => ['required', 'string']]);
        $family = Family::where('invite_code', strtoupper($data['invite_code']))->first();
        abort_unless($family, 404, 'Invalid invite code.');
        $request->user()->update(['family_id' => $family->id, 'family_role' => 'member']);
        return response()->json(['data' => $this->familyResource($family->load('members'))]);
    }

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->family_id, 404, 'Not in a family.');
        $family = $request->user()->family()->with('members')->first();
        return response()->json(['data' => $this->familyResource($family)]);
    }

    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->family_id, 422, 'Not in a family.');
        if ($user->isFamilyOwner() && $user->family->members()->count() > 1) {
            abort(422, 'Transfer ownership before leaving.');
        }
        if ($user->isFamilyOwner()) {
            $user->family->delete();
        }
        $user->update(['family_id' => null, 'family_role' => null]);
        return response()->json(['message' => 'Left family.']);
    }

    public function regenerateCode(Request $request): JsonResponse
    {
        abort_unless($request->user()->isFamilyAdmin(), 403, 'Admins only.');
        $code = $request->user()->family->regenerateInviteCode();
        return response()->json(['invite_code' => $code]);
    }

    public function removeMember(Request $request, User $member): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isFamilyAdmin(), 403, 'Admins only.');
        abort_unless($member->family_id === $user->family_id, 422, 'Not in your family.');
        abort_if($member->id === $user->id, 422, 'Cannot remove yourself.');
        $member->update(['family_id' => null, 'family_role' => null]);
        return response()->json(['message' => 'Member removed.']);
    }

    private function familyResource(Family $family): array
    {
        return [
            'id'          => $family->id,
            'name'        => $family->name,
            'invite_code' => $family->invite_code,
            'owner_id'    => $family->owner_id,
            'members'     => $family->relationLoaded('members')
                ? $family->members->map(fn($m) => [
                    'id'          => $m->id,
                    'name'        => $m->name,
                    'email'       => $m->email,
                    'family_role' => $m->family_role,
                ])->values()
                : [],
            'created_at' => $family->created_at?->toIso8601String(),
        ];
    }
}
