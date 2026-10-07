<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\FilingCheckController;
use App\Http\Controllers\LangController;
use App\Http\Controllers\OfferCompareController;
use App\Http\Controllers\ReturnGuideController;
use App\Http\Controllers\TargetTaxController;
use App\Http\Controllers\TdsPlannerController;
use App\Http\Controllers\TipsController;
use App\Http\Controllers\WealthController;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}.js', [LangController::class, 'script'])->where('locale', 'bn')->name('lang.script');

// Public: anyone can calculate. Saving needs an account.
Route::get('/', [CalculatorController::class, 'index'])->name('home');
Route::post('/calculate', [CalculatorController::class, 'calculate'])->middleware('throttle:240,1')->name('calculate');
Route::get('/target-tax', [TargetTaxController::class, 'index'])->name('target');
Route::post('/target-tax/solve', [TargetTaxController::class, 'solve'])->middleware('throttle:240,1')->name('target.solve');
Route::get('/return-guide', [ReturnGuideController::class, 'show'])->middleware('throttle:240,1')->name('return-guide');
Route::get('/tds-planner', [TdsPlannerController::class, 'index'])->name('tds');
Route::get('/must-i-file', [FilingCheckController::class, 'index'])->name('filing-check');
Route::get('/save-tax', [TipsController::class, 'index'])->name('tips');
Route::get('/compare-offers', [OfferCompareController::class, 'index'])->name('offers');
Route::post('/compare-offers/compute', [OfferCompareController::class, 'compute'])->middleware('throttle:240,1')->name('offers.compute');
Route::post('/tds-planner/plan', [TdsPlannerController::class, 'plan'])->middleware('throttle:240,1')->name('tds.plan');

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
    Route::get('/calculations/{calculation}/return-guide', [ReturnGuideController::class, 'forCalculation'])->whereNumber('calculation')->name('calculations.guide');

    Route::post('/target-tax/ratios', [TargetTaxController::class, 'saveRatios'])->name('target.ratios');

    Route::get('/wealth', [WealthController::class, 'index'])->name('wealth.index');
    Route::get('/wealth/{year}', [WealthController::class, 'edit'])->where('year', '\d{4}-\d{2}')->name('wealth.edit');
    Route::put('/wealth/{year}', [WealthController::class, 'update'])->where('year', '\d{4}-\d{2}')->name('wealth.update');
    Route::delete('/wealth/{year}', [WealthController::class, 'destroy'])->where('year', '\d{4}-\d{2}')->name('wealth.destroy');
    Route::get('/wealth/{year}/print', [WealthController::class, 'print'])->where('year', '\d{4}-\d{2}')->name('wealth.print');

    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
    Route::put('/account/tax', [AccountController::class, 'tax'])->name('account.tax');
    Route::put('/account/display', [AccountController::class, 'display'])->name('account.display');
});
