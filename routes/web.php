<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\BillController;
use App\Http\Controllers\Web\FamilyController;
use App\Http\Controllers\Web\IncomeController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ShoppingListController;
use App\Http\Controllers\Web\TwoFactorController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Temporary auth-check route (local only) - remove after debugging
if (app()->environment('local')) {
    Route::get('/_debug/auth', function () {
        return response()->json([
            'authenticated' => auth()->check(),
            'user' => auth()->user() ? auth()->user()->only('id', 'email', 'name') : null,
        ]);
    });
}

// Admin routes (user/category/provider management)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::resource('categories', App\Http\Controllers\Admin\CategoryController::class);
    Route::resource('providers', App\Http\Controllers\Admin\ProviderController::class);
    Route::post('products/lookup-barcode', [App\Http\Controllers\Admin\ProductController::class, 'lookupBarcode'])->name('products.lookup-barcode');
    Route::resource('products', App\Http\Controllers\Admin\ProductController::class);
});

Route::get('/login',  fn() => view('auth.login'))->name('login')->middleware('guest');
Route::post('/login', [DashboardController::class, 'login'])->name('login.post');
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/login');
})->name('logout');

// 2FA challenge (between password success and full auth)
Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('2fa.challenge')->middleware('guest');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->name('2fa.verify');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/month', [DashboardController::class, 'month'])->name('dashboard.month');
    Route::get('/locale/{lang}', [\App\Http\Controllers\Web\DashboardController::class, 'setLocale'])->name('locale.set');

    // Calendar events API (used by the inline calendar on the bills page)
    // Must be defined BEFORE the bills resource routes to avoid /{bill} pattern matching
    Route::get('/bills/events', [\App\Http\Controllers\Web\BillController::class, 'events'])->name('bills.events');

    // Bills
    Route::controller(BillController::class)
        ->prefix('bills')
        ->name('bills.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{bill}', 'show')->name('show');
            Route::get('/{bill}/edit', 'edit')->name('edit');
            Route::put('/{bill}', 'update')->name('update');
            Route::delete('/{bill}', 'destroy')->name('destroy');
            Route::post('/{bill}/pay', 'markPaid')->name('pay');
            // `unpay` reverses the latest payment only — it is the row-level
            // "undo what I just did". Removing an arbitrary historical payment
            // is a deliberate act and lives on its own route, reachable from the
            // bill's payment history.
            Route::delete('/{bill}/unpay', 'undoLastPayment')->name('unpay');
            Route::delete('/{bill}/payments/{payment}', 'destroyPayment')->name('payments.destroy');
        });

    // Accounts — where money actually sits. Incomes deposit into them, bill
    // payments withdraw from them, transfers move between them.
    Route::controller(App\Http\Controllers\Web\AccountController::class)
        ->prefix('accounts')
        ->name('accounts.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{account}', 'show')->name('show');
            Route::get('/{account}/edit', 'edit')->name('edit');
            Route::put('/{account}', 'update')->name('update');
            Route::delete('/{account}', 'destroy')->name('destroy');
            Route::post('/{account}/transfer', 'transfer')->name('transfer');
            Route::post('/{account}/movements', 'storeTransaction')->name('movements.store');
            Route::delete('/{account}/movements/{transaction}', 'destroyTransaction')->name('movements.destroy');
        });

    // Income
    Route::controller(IncomeController::class)
        ->prefix('income')
        ->name('income.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{income}', 'show')->name('show');
            Route::get('/{income}/edit', 'edit')->name('edit');
            Route::put('/{income}', 'update')->name('update');
            Route::delete('/{income}', 'destroy')->name('destroy');
            Route::post('/{income}/receive', 'markReceived')->name('receive');
        });

    // Family
    Route::controller(FamilyController::class)
        ->prefix('family')
        ->name('family.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'create')->name('create');
            Route::post('/join', 'join')->name('join');
            Route::delete('/leave', 'leave')->name('leave');
            Route::post('/regenerate-code', 'regenerateCode')->name('regenerate');
            Route::delete('/members/{member}', 'removeMember')->name('remove');
            Route::post('/members/{member}/transfer', 'transferOwnership')->name('transfer');
        });
});

// User settings (profile)
Route::middleware('auth')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\Web\DashboardController::class, 'settings'])->name('settings');
    Route::post('/settings', [\App\Http\Controllers\Web\DashboardController::class, 'updateSettings'])->name('settings.update');

    // Web Push subscriptions — one row per browser that granted permission.
    Route::controller(\App\Http\Controllers\Web\PushSubscriptionController::class)
        ->prefix('push')
        ->name('push.')
        ->group(function () {
            Route::get('/config', 'config')->name('config');
            Route::post('/subscribe', 'store')->name('subscribe');
            Route::delete('/subscribe', 'destroy')->name('unsubscribe');
        });

    // 2FA setup
    Route::get('/two-factor-setup', [TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/two-factor-enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/two-factor-disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');

    // Translations management
    Route::prefix('translations')->name('translations.')->controller(\App\Http\Controllers\Web\TranslationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{translation}/edit', 'edit')->name('edit');
        Route::put('/{translation}', 'update')->name('update');
        Route::delete('/{translation}', 'destroy')->name('destroy');
    });

    // Shopping Lists
    Route::controller(ShoppingListController::class)
        ->prefix('shopping-lists')
        ->name('shopping-list.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{list}', 'show')->name('show');
        });

    // Products catalog
    // Products — the shared catalogue behind shopping list items.
    Route::controller(ProductController::class)
        ->prefix('products')
        ->name('products.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{product}', 'show')->name('show');
            Route::get('/{product}/edit', 'edit')->name('edit');
            Route::put('/{product}', 'update')->name('update');
            Route::delete('/{product}', 'destroy')->name('destroy');
            Route::post('/{product}/refresh', 'refresh')->name('refresh');
        });

    // Turn a hand-typed shopping list line into a catalogue entry.
    Route::post('/shopping-list/items/{item}/promote', [ProductController::class, 'promote'])
        ->name('products.promote');

    // Recipes
    // Import must be declared before the resource routes so `/recipes/import`
    // isn't swallowed by the `/recipes/{recipe}` show route.
    Route::post('/recipes/import', [App\Http\Controllers\Web\RecipeController::class, 'import'])
        ->middleware('throttle:10,1')
        ->name('recipes.import');
    // Photos are stored the moment they're chosen so the form can preview them
    // and so uploads and imports both hand the form the same `image_path`.
    Route::post('/recipes/image', [App\Http\Controllers\Web\RecipeController::class, 'uploadImage'])
        ->middleware('throttle:20,1')
        ->name('recipes.image.upload');
    Route::delete('/recipes/{recipe}/image', [App\Http\Controllers\Web\RecipeController::class, 'destroyImage'])->name('recipes.image.destroy');
    Route::post('/recipes/{recipe}/favorite', [App\Http\Controllers\Web\RecipeController::class, 'toggleFavorite'])->name('recipes.favorite');
    Route::post('/recipes/{recipe}/to-shopping-list', [App\Http\Controllers\Web\RecipeController::class, 'toShoppingList'])->name('recipes.to-shopping-list');
    Route::resource('recipes', App\Http\Controllers\Web\RecipeController::class);

    // Meal planner
    Route::controller(App\Http\Controllers\Web\MealPlanController::class)
        ->prefix('meal-planner')
        ->name('meal-plans.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{mealPlan}', 'update')->name('update');
            Route::delete('/{mealPlan}', 'destroy')->name('destroy');
            Route::post('/to-shopping-list', 'toShoppingList')->name('to-shopping-list');
        });
});
