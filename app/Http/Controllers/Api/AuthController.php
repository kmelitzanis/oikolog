<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request, TwoFactorVerifier $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
            'code'        => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => __('messages.invalid_credentials')], 401);
        }

        // A token is a full sign-in, so it has to clear the same second factor
        // the web login asks for — otherwise 2FA is one API call away from
        // being skipped. The client learns to ask for the code from the 422.
        if ($user->two_factor_enabled) {
            if (blank($data['code'] ?? null)) {
                return response()->json([
                    'message'             => __('messages.two_factor_required'),
                    'two_factor_required' => true,
                ], 422);
            }

            if (! $twoFactor->verify($user, $data['code'])) {
                return response()->json(['message' => __('messages.two_factor_invalid_code')], 401);
            }
        }

        if (isset($data['device_name'])) {
            $user->tokens()->where('name', $data['device_name'])->delete();
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'mobile', ['*'], now()->addYear()
        )->plainTextToken;

        return response()->json([
            'user'  => $this->userResource($user->load('family')),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // A cookie-authenticated SPA request carries a TransientToken, which
        // has nothing to delete.
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'All sessions revoked.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userResource($request->user()->load('family')),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'max:100'],
            'currency_code'         => ['sometimes', 'string', 'size:3'],
            'timezone'              => ['sometimes', 'timezone:all'],
            'notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update($data);

        return response()->json([
            'user' => $this->userResource($request->user()->fresh()->load('family')),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->update(['password' => Hash::make($data['password'])]);

        // Every other token was issued against the old password.
        $current = $user->currentAccessToken();
        $user->tokens()
            ->when($current && isset($current->id), fn ($q) => $q->whereKeyNot($current->id))
            ->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    private function userResource(User $user): array
    {
        return [
            'id'                    => $user->id,
            'name'                  => $user->name,
            'email'                 => $user->email,
            'avatar_url'            => $user->avatar_url,
            'currency_code'         => $user->currency_code,
            'timezone'              => $user->timezone,
            'notifications_enabled' => $user->notifications_enabled,
            'family_id'             => $user->family_id,
            'family_role'           => $user->family_role,
            'family'                => $user->relationLoaded('family') && $user->family ? [
                'id'          => $user->family->id,
                'name'        => $user->family->name,
                'invite_code' => $user->family->invite_code,
            ] : null,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
