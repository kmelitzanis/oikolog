<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\BillController;
use App\Http\Controllers\Web\FamilyController;
use App\Http\Controllers\Web\IncomeController;
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

// Admin routes (user/category management)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::resource('categories', App\Http\Controllers\Admin\CategoryController::class);
});

Route::get('/login',    fn() => view('auth.login'))->name('login')->middleware('guest');
Route::get('/register', fn() => view('auth.register'))->name('register')->middleware('guest');
Route::post('/login',    [DashboardController::class, 'login'])->name('login.post');
Route::post('/register', [DashboardController::class, 'register'])->name('register.post');
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/login');
})->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/locale/{lang}', [\App\Http\Controllers\Web\DashboardController::class, 'setLocale'])->name('locale.set');

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
            Route::delete('/{bill}/unpay', 'undoLastPayment')->name('unpay');
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

    // Calendar
    Route::get('/calendar', [\App\Http\Controllers\Web\BillController::class, 'calendar'])->name('calendar');
    Route::get('/bills/events', [\App\Http\Controllers\Web\BillController::class, 'events'])->name('bills.events');

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

    // Translations management
    Route::prefix('translations')->name('translations.')->controller(\App\Http\Controllers\Web\TranslationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{translation}/edit', 'edit')->name('edit');
        Route::put('/{translation}', 'update')->name('update');
        Route::delete('/{translation}', 'destroy')->name('destroy');
    });
});
