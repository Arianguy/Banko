<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FixedDepositController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('fixed-deposits', function () {
        return Inertia::render('FixedDeposits/Index');
    })->name('fixed-deposits');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('fixed-deposits', [FixedDepositController::class, 'index'])->name('fixed-deposits');
        Route::post('fixed-deposits', [FixedDepositController::class, 'store'])->name('fixed-deposits.store');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
