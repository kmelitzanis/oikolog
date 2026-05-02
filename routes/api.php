<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\ShoppingListController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
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

    Route::prefix('shopping-lists')->group(function () {
        Route::get('/',                             [ShoppingListController::class, 'index']);
        Route::post('/',                            [ShoppingListController::class, 'store']);
        Route::get('/{list}',                       [ShoppingListController::class, 'show']);
        Route::put('/{list}',                       [ShoppingListController::class, 'update']);
        Route::delete('/{list}',                    [ShoppingListController::class, 'destroy']);
        Route::post('/{list}/items',                [ShoppingListController::class, 'addItem']);
        Route::put('/{list}/items/{item}',          [ShoppingListController::class, 'updateItem']);
        Route::delete('/{list}/items/{item}',       [ShoppingListController::class, 'removeItem']);
        Route::patch('/{list}/items/{item}/toggle', [ShoppingListController::class, 'toggleItem']);
    });

    Route::post('/shopping-lists/lookup-barcode', [ShoppingListController::class, 'lookupBarcode']);
});
