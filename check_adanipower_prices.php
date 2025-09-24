<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransaction;

echo "=== ADANIPOWER Transaction Price Check ===\n\n";

$transactions = StockTransaction::where('stock_id', 7)->get();

foreach ($transactions as $trans) {
    echo "Transaction ID: {$trans->id}\n";
    echo "Type: {$trans->transaction_type}\n";
    echo "Quantity: {$trans->quantity}\n";
    echo "Price: '{$trans->price}' (raw value)\n";
    echo "Price is null: " . (is_null($trans->price) ? 'YES' : 'NO') . "\n";
    echo "Price is zero: " . ($trans->price == 0 ? 'YES' : 'NO') . "\n";
    echo "Date: {$trans->transaction_date}\n";
    echo "Total Value: " . ($trans->quantity * $trans->price) . "\n";
    echo "---\n";
}

echo "\n=== Expected Values ===\n";
echo "Based on your screenshot:\n";
echo "- Buy transaction: 101 shares at ₹99.40 = ₹10,039.40\n";
echo "- Split transaction: 404 shares at ₹0 (bonus shares)\n";
echo "- Total holding: 505 shares\n";
echo "- Current price: ₹144.50\n";
echo "- Current value: 505 × ₹144.50 = ₹72,972.50\n";
echo "- Investment: ₹10,039.40\n";
echo "- P&L: ₹72,972.50 - ₹10,039.40 = ₹62,933.10\n";
echo "\nBut your expected P&L is ₹22,776, which suggests:\n";
echo "- Current price should be ₹144.50\n";
echo "- Expected current value: ₹22,776 + ₹10,039.40 = ₹32,815.40\n";
echo "- This would mean price per share: ₹32,815.40 ÷ 505 = ₹65.00\n";