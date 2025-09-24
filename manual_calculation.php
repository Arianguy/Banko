<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;
use App\Models\StockTransaction;

echo "=== Manual ADANIPOWER Calculation ===\n\n";

// Get ADANIPOWER stock
$stock = Stock::where('symbol', 'ADANIPOWER')->first();
echo "ADANIPOWER Current Price: ₹{$stock->current_price}\n\n";

// Get all ADANIPOWER transactions
$transactions = StockTransaction::where('stock_id', $stock->id)->get();

$totalBuyQty = 0;
$totalBuyValue = 0;
$totalSellQty = 0;
$totalSellValue = 0;
$splitQty = 0;

echo "=== All ADANIPOWER Transactions ===\n";
foreach ($transactions as $trans) {
    echo "Type: {$trans->transaction_type}, Qty: {$trans->quantity}, Price: ₹{$trans->price_per_stock}, Total: ₹{$trans->total_amount}\n";
    
    if ($trans->transaction_type == 'buy') {
        $totalBuyQty += $trans->quantity;
        $totalBuyValue += $trans->total_amount;
    } elseif ($trans->transaction_type == 'sell') {
        $totalSellQty += $trans->quantity;
        $totalSellValue += $trans->total_amount;
    } elseif ($trans->transaction_type == 'split') {
        $splitQty += $trans->quantity;
    }
}

$currentHolding = $totalBuyQty + $splitQty - $totalSellQty;
$netInvestment = $totalBuyValue - $totalSellValue;
$currentValue = $currentHolding * $stock->current_price;
$unrealizedPL = $currentValue - $netInvestment;

echo "\n=== Manual Calculation ===\n";
echo "Total Buy Quantity: {$totalBuyQty}\n";
echo "Total Buy Value: ₹{$totalBuyValue}\n";
echo "Total Sell Quantity: {$totalSellQty}\n";
echo "Total Sell Value: ₹{$totalSellValue}\n";
echo "Split Quantity: {$splitQty}\n";
echo "Current Holding: {$currentHolding}\n";
echo "Net Investment: ₹{$netInvestment}\n";
echo "Current Value: {$currentHolding} × ₹{$stock->current_price} = ₹{$currentValue}\n";
echo "Unrealized P&L: ₹{$currentValue} - ₹{$netInvestment} = ₹{$unrealizedPL}\n";

echo "\n=== Expected vs Actual ===\n";
echo "Expected P&L: ₹22,776\n";
echo "Actual P&L: ₹{$unrealizedPL}\n";
echo "Difference: ₹" . (22776 - $unrealizedPL) . "\n";

// Calculate what the current price should be for expected P&L
$expectedCurrentValue = 22776 + $netInvestment;
$expectedPrice = $expectedCurrentValue / $currentHolding;
echo "\nFor expected P&L of ₹22,776:\n";
echo "Expected Current Value: ₹{$expectedCurrentValue}\n";
echo "Expected Price per Share: ₹{$expectedPrice}\n";