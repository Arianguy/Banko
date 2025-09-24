<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransaction;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;

echo "=== ADANIPOWER P&L Analysis ===\n\n";

// Get user and authenticate
$user = \App\Models\User::first();
Auth::login($user);

echo "User: {$user->email}\n\n";

// Get ADANIPOWER stock
$adaniStock = Stock::where('symbol', 'ADANIPOWER')->first();
if (!$adaniStock) {
    echo "ADANIPOWER stock not found!\n";
    exit;
}

echo "Stock: {$adaniStock->symbol} - Current Price: ₹{$adaniStock->current_price}\n\n";

// Get all ADANIPOWER transactions
$transactions = StockTransaction::where('user_id', $user->id)
    ->where('stock_id', $adaniStock->id)
    ->orderBy('transaction_date')
    ->get();

echo "=== All ADANIPOWER Transactions ===\n";
echo "Found {$transactions->count()} transactions for ADANIPOWER\n";

// Let's also check all transactions for this user
$allTransactions = StockTransaction::where('user_id', $user->id)->with('stock')->get();
echo "Total transactions for user: {$allTransactions->count()}\n";

echo "\nAll user transactions:\n";
foreach ($allTransactions as $trans) {
    echo "- {$trans->stock->symbol}: {$trans->transaction_type}, Qty: {$trans->quantity}, Price: ₹{$trans->price}\n";
}
$totalBuyQuantity = 0;
$totalBuyValue = 0;
$totalSellQuantity = 0;
$totalSellValue = 0;
$splitQuantity = 0;

foreach ($transactions as $transaction) {
    echo "Date: {$transaction->transaction_date}, Type: {$transaction->transaction_type}, ";
    echo "Qty: {$transaction->quantity}, Price: ₹{$transaction->price}, ";
    echo "Total: ₹" . ($transaction->quantity * $transaction->price) . "\n";
    
    if ($transaction->transaction_type === 'buy') {
        $totalBuyQuantity += $transaction->quantity;
        $totalBuyValue += ($transaction->quantity * $transaction->price);
    } elseif ($transaction->transaction_type === 'sell') {
        $totalSellQuantity += $transaction->quantity;
        $totalSellValue += ($transaction->quantity * $transaction->price);
    } elseif ($transaction->transaction_type === 'split') {
        $splitQuantity += $transaction->quantity;
    }
}

echo "\n=== Summary ===\n";
echo "Total Buy Quantity: {$totalBuyQuantity}\n";
echo "Total Buy Value: ₹{$totalBuyValue}\n";
echo "Total Sell Quantity: {$totalSellQuantity}\n";
echo "Total Sell Value: ₹{$totalSellValue}\n";
echo "Split Quantity: {$splitQuantity}\n";

$currentHolding = $totalBuyQuantity + $splitQuantity - $totalSellQuantity;
echo "Current Holding: {$currentHolding} shares\n";

$currentValue = $currentHolding * $adaniStock->current_price;
echo "Current Value: ₹{$currentValue}\n";

$investment = $totalBuyValue - $totalSellValue;
echo "Net Investment: ₹{$investment}\n";

$unrealizedPL = $currentValue - $investment;
echo "Unrealized P&L: ₹{$unrealizedPL}\n\n";

// Now let's check what the dashboard controller calculates
echo "=== Dashboard Controller Calculation ===\n";

$dashboardController = new \App\Http\Controllers\DashboardController();

try {
    $reflection = new ReflectionClass($dashboardController);
    $method = $reflection->getMethod('getEquityData');
    $method->setAccessible(true);
    
    $equityData = $method->invoke($dashboardController);
    
    echo "Dashboard Total Invested: ₹{$equityData['total_invested']}\n";
    echo "Dashboard Current Value: ₹{$equityData['current_value']}\n";
    echo "Dashboard Unrealized P&L: ₹{$equityData['unrealized_pl']}\n\n";
    
    // Let's also check individual stock calculation
    $calculateMethod = $reflection->getMethod('calculateCurrentHoldings');
    $calculateMethod->setAccessible(true);
    
    $holdings = $calculateMethod->invoke($dashboardController, $user->id, now());
    
    echo "=== Individual Stock Holdings ===\n";
    foreach ($holdings as $holding) {
        if ($holding['symbol'] === 'ADANIPOWER') {
            echo "ADANIPOWER from Dashboard:\n";
            echo "- Quantity: {$holding['quantity']}\n";
            echo "- Avg Price: ₹{$holding['avgPrice']}\n";
            echo "- Current Price: ₹{$holding['currentPrice']}\n";
            echo "- Investment: ₹{$holding['totalInvestment']}\n";
            echo "- Current Value: ₹{$holding['currentValue']}\n";
            echo "- Unrealized P&L: ₹{$holding['unrealizedGainLoss']}\n";
            break;
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Expected vs Actual ===\n";
echo "Expected P&L: ₹22,776\n";
echo "Actual P&L: ₹{$unrealizedPL}\n";
echo "Dashboard P&L: ₹{$equityData['unrealized_pl']}\n";

if ($unrealizedPL != 22776) {
    echo "\n❌ DISCREPANCY FOUND!\n";
    echo "Difference: ₹" . ($unrealizedPL - 22776) . "\n";
} else {
    echo "\n✅ Values match expected!\n";
}

echo "\n=== End Analysis ===\n";