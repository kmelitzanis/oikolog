<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\IncomeController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

Route::get('/health', fn() => response()->json(['status' => 'ok', 'version' => '1.0']));

// ── Authenticated ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum,web'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('/me',          [AuthController::class, 'me']);
        Route::put('/profile',     [AuthController::class, 'updateProfile']);
        Route::put('/password',    [AuthController::class, 'updatePassword']);
        Route::post('/logout',     [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });

    Route::prefix('bills')->group(function () {
        Route::get('/stats',           [BillController::class, 'stats']);
        Route::get('/series', [BillController::class, 'series']);
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

    Route::prefix('income')->group(function () {
        Route::get('/stats', [IncomeController::class, 'stats']);
        Route::get('/', [IncomeController::class, 'index']);
        Route::post('/', [IncomeController::class, 'store']);
        Route::get('/{income}', [IncomeController::class, 'show']);
        Route::put('/{income}', [IncomeController::class, 'update']);
        Route::delete('/{income}', [IncomeController::class, 'destroy']);
        Route::post('/{income}/receive', [IncomeController::class, 'markReceived']);
    });

    Route::prefix('family')->group(function () {
        Route::get('/',                      [FamilyController::class, 'show']);
        Route::post('/',                     [FamilyController::class, 'create']);
        Route::post('/join',                 [FamilyController::class, 'join']);
        Route::delete('/leave',              [FamilyController::class, 'leave']);
        Route::post('/regenerate-code',      [FamilyController::class, 'regenerateCode']);
        Route::delete('/members/{member}',   [FamilyController::class, 'removeMember']);
    });
});
