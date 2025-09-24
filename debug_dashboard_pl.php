<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransaction;
use App\Models\Stock;

echo "=== Dashboard P/L Debug ===\n\n";

// Check transaction types
echo "Transaction types in database:\n";
$transactionTypes = StockTransaction::select('transaction_type')->distinct()->get();
foreach ($transactionTypes as $type) {
    $count = StockTransaction::where('transaction_type', $type->transaction_type)->count();
    echo "- {$type->transaction_type}: {$count} transactions\n";
}

echo "\n";

// Check split transactions specifically
$splitTransactions = StockTransaction::where('transaction_type', 'split')->with('stock')->get();
echo "Split transactions found: " . $splitTransactions->count() . "\n";

if ($splitTransactions->count() > 0) {
    echo "\nSplit transaction details:\n";
    foreach ($splitTransactions as $split) {
        echo "- Stock: {$split->stock->symbol}, Quantity: {$split->quantity}, Date: {$split->transaction_date}\n";
    }
}

echo "\n";

// Check current holdings calculation
echo "=== Current Holdings Analysis ===\n";
$userId = 1; // Assuming user ID 1, adjust as needed

$transactions = StockTransaction::with('stock')
    ->where('user_id', $userId)
    ->get()
    ->groupBy('stock.symbol');

foreach ($transactions as $symbol => $stockTransactions) {
    echo "\nStock: {$symbol}\n";
    
    $sortedTransactions = $stockTransactions->sortBy(['transaction_date', 'id']);
    
    $buyQueue = collect();
    $totalInvestment = 0;
    
    echo "Processing transactions:\n";
    foreach ($sortedTransactions as $transaction) {
        echo "- {$transaction->transaction_type}: {$transaction->quantity} shares on {$transaction->transaction_date}\n";
        
        if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
            $totalInvestment += $transaction->net_amount;
            echo "  Added to investment: {$transaction->net_amount}, Total: {$totalInvestment}\n";
        } elseif ($transaction->transaction_type === 'split') {
            echo "  SPLIT TRANSACTION - Currently NOT being processed in dashboard!\n";
        }
    }
    
    $totalQuantity = $buyQueue->sum('remaining');
    $stock = $stockTransactions->first()->stock;
    $currentPrice = $stock->current_price ?? 0;
    $currentValue = $totalQuantity * $currentPrice;
    
    echo "Final calculation:\n";
    echo "- Total Investment: {$totalInvestment}\n";
    echo "- Total Quantity: {$totalQuantity}\n";
    echo "- Current Price: {$currentPrice}\n";
    echo "- Current Value: {$currentValue}\n";
    echo "- Unrealized P/L: " . ($currentValue - $totalInvestment) . "\n";
}

// Calculate the impact of missing split transactions
echo "\n=== Impact Analysis ===\n";

$adaniStock = Stock::where('symbol', 'ADANIPOWER')->first();
if ($adaniStock) {
    $currentPrice = $adaniStock->current_price;
    $splitShares = 404;
    $missingValue = $splitShares * $currentPrice;
    
    echo "ADANIPOWER current price: {$currentPrice}\n";
    echo "Missing split shares: {$splitShares}\n";
    echo "Missing current value: {$missingValue}\n";
    echo "Current wrong P/L for ADANIPOWER: -50197\n";
    echo "Correct P/L should be: " . ($missingValue - 50197) . "\n";
    echo "Difference: " . ($missingValue - 50197 + 50197) . " = {$missingValue}\n";
}

echo "\n=== End Debug ===\n";