<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FamilyController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

Route::get('/health', fn() => response()->json(['status' => 'ok', 'version' => '1.0']));

// ── Authenticated ─────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('/me',          [AuthController::class, 'me']);
        Route::put('/profile',     [AuthController::class, 'updateProfile']);
        Route::put('/password',    [AuthController::class, 'updatePassword']);
        Route::post('/logout',     [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    Route::prefix('bills')->group(function () {
        Route::get('/stats',           [BillController::class, 'stats']);
        Route::get('/',                [BillController::class, 'index']);
        Route::post('/',               [BillController::class, 'store']);
        Route::get('/{bill}',          [BillController::class, 'show']);
        Route::put('/{bill}',          [BillController::class, 'update']);
        Route::delete('/{bill}',       [BillController::class, 'destroy']);
        Route::post('/{bill}/pay',     [BillController::class, 'markPaid']);
        Route::get('/{bill}/payments', [BillController::class, 'payments']);
    });

    Route::get('/categories',          [CategoryController::class, 'index']);
    Route::post('/categories',         [CategoryController::class, 'store']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::prefix('family')->group(function () {
        Route::get('/',                      [FamilyController::class, 'show']);
        Route::post('/',                     [FamilyController::class, 'create']);
        Route::post('/join',                 [FamilyController::class, 'join']);
        Route::delete('/leave',              [FamilyController::class, 'leave']);
        Route::post('/regenerate-code',      [FamilyController::class, 'regenerateCode']);
        Route::delete('/members/{member}',   [FamilyController::class, 'removeMember']);
    });
});
