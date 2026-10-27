<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\MealieRecipeController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The API key SparkyFitness uses to read this user's recipes.
 *
 * One key per user: making a new one revokes the old, so a key pasted
 * somewhere and forgotten does not keep working. It may read recipes and
 * nothing else. The plain text is shown once, right after it is made;
 * only its hash is kept.
 */
class SparkyFitnessController extends Controller
{
    public const TOKEN_NAME = 'SparkyFitness';

    public function store(Request $request)
    {
        $user = $request->user();

        $user->tokens()->where('name', self::TOKEN_NAME)->delete();
        $token = $user->createToken(self::TOKEN_NAME, [MealieRecipeController::ABILITY]);

        return redirect()->route('settings')
            ->with('sparky_token', $token->plainTextToken)
            ->with('success', __('messages.sparky_token_created'));
    }

    public function destroy(Request $request)
    {
        $request->user()->tokens()->where('name', self::TOKEN_NAME)->delete();

        return redirect()->route('settings')->with('success', __('messages.sparky_token_revoked'));
    }
}
