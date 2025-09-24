<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransaction;
use App\Models\Stock;

echo "=== Stock ID Debug ===\n\n";

// Get all stocks
$stocks = Stock::all();
echo "All stocks in database:\n";
foreach ($stocks as $stock) {
    echo "ID: {$stock->id}, Symbol: {$stock->symbol}, Name: {$stock->name}\n";
}

echo "\n=== All Stock Transactions ===\n";
$transactions = StockTransaction::with('stock')->get();
foreach ($transactions as $transaction) {
    echo "Transaction ID: {$transaction->id}, Stock ID: {$transaction->stock_id}, ";
    echo "Stock Symbol: {$transaction->stock->symbol}, Type: {$transaction->transaction_type}, ";
    echo "Qty: {$transaction->quantity}, Price: ₹{$transaction->price}\n";
}

// Check ADANIPOWER specifically
echo "\n=== ADANIPOWER Analysis ===\n";
$adaniStock = Stock::where('symbol', 'ADANIPOWER')->first();
if ($adaniStock) {
    echo "ADANIPOWER Stock ID: {$adaniStock->id}\n";
    echo "ADANIPOWER Current Price: ₹{$adaniStock->current_price}\n";
    
    $adaniTransactions = StockTransaction::where('stock_id', $adaniStock->id)->get();
    echo "ADANIPOWER Transactions: {$adaniTransactions->count()}\n";
    
    foreach ($adaniTransactions as $trans) {
        echo "- Type: {$trans->transaction_type}, Qty: {$trans->quantity}, Price: ₹{$trans->price}, Date: {$trans->transaction_date}\n";
    }
} else {
    echo "ADANIPOWER stock not found!\n";
}

echo "\n=== End Debug ===\n";