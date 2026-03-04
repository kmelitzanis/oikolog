<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\BillController;
use App\Http\Controllers\Web\FamilyController;
use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
        });
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                            [AdminController::class, 'index'])->name('dashboard');
    Route::get('/users',                       [AdminController::class, 'users'])->name('users');
    Route::delete('/users/{user}',             [AdminController::class, 'deleteUser'])->name('users.delete');
    Route::get('/categories',                  [AdminController::class, 'categories'])->name('categories');
    Route::post('/categories',                 [AdminController::class, 'storeCategory'])->name('categories.store');
    Route::delete('/categories/{category}',    [AdminController::class, 'deleteCategory'])->name('categories.delete');
});
