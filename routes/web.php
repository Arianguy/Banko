<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FixedDepositController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\EquityHoldingController;

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

        // Equity Holding Routes
        Route::get('/equity-holding', [EquityHoldingController::class, 'index'])->name('equity-holding.index');
        Route::post('/equity-holding', [EquityHoldingController::class, 'store'])->name('equity-holding.store');
        Route::post('/equity-holding/sync-prices', [EquityHoldingController::class, 'syncPrices'])->name('equity-holding.sync-prices');
        Route::put('/equity-holding/transactions/{transactionId}', [EquityHoldingController::class, 'updateTransaction'])->name('equity-holding.update-transaction');
        Route::get('/equity-holding/{stockId}/transactions', [EquityHoldingController::class, 'getTransactions'])->name('equity-holding.transactions');
        Route::get('/equity-holding/{stockId}/holding-info', [EquityHoldingController::class, 'getHoldingInfo'])->name('equity-holding.holding-info');
        Route::get('/equity-holding/search-stocks', [EquityHoldingController::class, 'searchStocks'])->name('equity-holding.search-stocks');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
