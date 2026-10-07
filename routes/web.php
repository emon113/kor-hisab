<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\LangController;
use App\Http\Controllers\TargetTaxController;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}.js', [LangController::class, 'script'])->where('locale', 'bn')->name('lang.script');

// Public: anyone can calculate. Saving needs an account.
Route::get('/', [CalculatorController::class, 'index'])->name('home');
Route::post('/calculate', [CalculatorController::class, 'calculate'])->middleware('throttle:240,1')->name('calculate');
Route::get('/target-tax', [TargetTaxController::class, 'index'])->name('target');
Route::post('/target-tax/solve', [TargetTaxController::class, 'solve'])->middleware('throttle:240,1')->name('target.solve');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/calculations', [CalculationController::class, 'index'])->name('calculations.index');
    Route::get('/calculations/compare', [CalculationController::class, 'compare'])->name('calculations.compare');
    Route::post('/calculations', [CalculationController::class, 'store'])->name('calculations.store');
    Route::get('/calculations/{calculation}', [CalculatorController::class, 'show'])->whereNumber('calculation')->name('calculations.show');
    Route::put('/calculations/{calculation}', [CalculationController::class, 'update'])->whereNumber('calculation')->name('calculations.update');
    Route::delete('/calculations/{calculation}', [CalculationController::class, 'destroy'])->whereNumber('calculation')->name('calculations.destroy');

    Route::post('/target-tax/ratios', [TargetTaxController::class, 'saveRatios'])->name('target.ratios');

    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
});
