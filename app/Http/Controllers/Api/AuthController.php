<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', Password::min(8)],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone'      => ['nullable', 'timezone:all'],
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'currency_code' => $data['currency_code'] ?? 'EUR',
            'timezone'      => $data['timezone'] ?? 'Europe/Athens',
        ]);

        $token = $user->createToken('mobile', ['*'], now()->addYear())->plainTextToken;

        return response()->json(['user' => $this->userResource($user), 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
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
        $request->user()->currentAccessToken()->delete();
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

        $request->user()->update(['password' => Hash::make($data['password'])]);

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
                'invite_code' => $user->isFamilyAdmin() ? $user->family->invite_code : null,
            ] : null,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
