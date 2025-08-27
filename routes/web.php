<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FixedDepositController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BankBalanceController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\EquityHoldingController;
use App\Http\Controllers\MutualFundController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Debug route to check dashboard data
    Route::get('debug-dashboard', function () {
        $controller = new DashboardController();
        $request = new \Illuminate\Http\Request();
        $result = $controller->index($request);
        return response()->json($result->getData());
    })->name('debug.dashboard');
    
    Route::get('fixed-deposits', function () {
        return Inertia::render('FixedDeposits/Index');
    })->name('fixed-deposits');

    Route::get('/portfolio-summary', function () {
        return Inertia::render('Portfolio/Summary');
    })->name('portfolio.summary');
        Route::get('fixed-deposits', [FixedDepositController::class, 'index'])->name('fixed-deposits');
        Route::post('fixed-deposits', [FixedDepositController::class, 'store'])->name('fixed-deposits.store');
        Route::put('fixed-deposits/{id}/mature', [FixedDepositController::class, 'mature'])->name('fixed-deposits.mature');
        Route::put('fixed-deposits/{id}/close', [FixedDepositController::class, 'close'])->name('fixed-deposits.close');
        Route::put('fixed-deposits/{id}', [FixedDepositController::class, 'update'])->name('fixed-deposits.update');
        Route::post('/banks', [BankController::class, 'store']);
        Route::get('/banks', [BankController::class, 'index']);
        
        // Bank Balance Routes
        Route::post('/bank-balances', [BankBalanceController::class, 'store'])->name('bank-balances.store');
        
        // Bank Account Routes
        Route::get('/banks/{bankId}/accounts', [BankBalanceController::class, 'getAccountsByBank'])->name('banks.accounts');
        Route::post('/bank-accounts', [BankBalanceController::class, 'storeAccount'])->name('bank-accounts.store');

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
        Route::get('/equity-holding/sold-history', [EquityHoldingController::class, 'getSoldHistory'])->name('equity-holding.sold-history');

        // Dividend Routes
        Route::post('/equity-holding/update-dividend-data', [EquityHoldingController::class, 'updateDividendData'])->name('equity-holding.update-dividend-data');
        Route::get('/equity-holding/{stockId}/dividend-details', [EquityHoldingController::class, 'getDividendDetails'])->name('equity-holding.dividend-details');
        Route::post('/equity-holding/mark-dividend-received', [EquityHoldingController::class, 'markDividendReceived'])->name('equity-holding.mark-dividend-received');

        // Mutual Fund Routes
        Route::get('/mutual-funds', [MutualFundController::class, 'index'])->name('mutual-funds.index');
        Route::post('/mutual-funds', [MutualFundController::class, 'store'])->name('mutual-funds.store');
        Route::post('/mutual-funds/add-fund', [MutualFundController::class, 'addFund'])->name('mutual-funds.add-fund');
        Route::post('/mutual-funds/sync-navs', [MutualFundController::class, 'syncNavs'])->name('mutual-funds.sync-navs');
        Route::put('/mutual-funds/transactions/{id}', [MutualFundController::class, 'update'])->name('mutual-funds.transactions.update');
        Route::delete('/mutual-funds/transactions/{id}', [MutualFundController::class, 'destroy'])->name('mutual-funds.transactions.destroy');
        Route::get('/mutual-funds/search-funds', [MutualFundController::class, 'searchFunds'])->name('mutual-funds.search-funds');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
