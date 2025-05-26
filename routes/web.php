<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FixedDepositController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\PortfolioController;

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

    Route::get('/portfolio-summary', function () {
        return Inertia::render('Portfolio/Summary');
    })->name('portfolio.summary');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('fixed-deposits', [FixedDepositController::class, 'index'])->name('fixed-deposits');
        Route::post('fixed-deposits', [FixedDepositController::class, 'store'])->name('fixed-deposits.store');
        Route::put('fixed-deposits/{id}/mature', [FixedDepositController::class, 'mature'])->name('fixed-deposits.mature');
        Route::put('fixed-deposits/{id}/close', [FixedDepositController::class, 'close'])->name('fixed-deposits.close');
        Route::put('fixed-deposits/{id}', [FixedDepositController::class, 'update'])->name('fixed-deposits.update');        
        Route::post('/banks', [BankController::class, 'store']);
        Route::get('/banks', [BankController::class, 'index']);

        // Portfolio Routes
        Route::get('/api/portfolio/summary-metrics', [PortfolioController::class, 'getSummaryMetrics'])->name('api.portfolio.summary');
        Route::get('/api/portfolio/bank-distribution', [PortfolioController::class, 'getBankDistribution'])->name('api.portfolio.bankDistribution');
        Route::get('/api/portfolio/maturity-year-breakdown', [PortfolioController::class, 'getMaturityYearBreakdown'])->name('api.portfolio.maturityBreakdown');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
