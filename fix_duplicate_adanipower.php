<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;
use App\Models\StockTransaction;

echo "=== Fixing Duplicate ADANIPOWER Records ===\n\n";

// Get both ADANIPOWER records
$adani7 = Stock::find(7);
$adani8 = Stock::find(8);

echo "Stock ID 7: {$adani7->symbol} - Current Price: ₹{$adani7->current_price}\n";
echo "Stock ID 8: {$adani8->symbol} - Current Price: ₹{$adani8->current_price}\n\n";

// Check transactions for each
$transactions7 = StockTransaction::where('stock_id', 7)->get();
$transactions8 = StockTransaction::where('stock_id', 8)->get();

echo "Transactions for Stock ID 7: {$transactions7->count()}\n";
echo "Transactions for Stock ID 8: {$transactions8->count()}\n\n";

// Show transactions for ID 7
echo "Transactions for Stock ID 7 (ADANIPOWER):\n";
foreach ($transactions7 as $trans) {
    echo "- Type: {$trans->transaction_type}, Qty: {$trans->quantity}, Price: ₹{$trans->price}, Date: {$trans->transaction_date}\n";
}

echo "\nTransactions for Stock ID 8 (ADANIPOWER):\n";
foreach ($transactions8 as $trans) {
    echo "- Type: {$trans->transaction_type}, Qty: {$trans->quantity}, Price: ₹{$trans->price}, Date: {$trans->transaction_date}\n";
}

// Decision: Keep the one with transactions (ID 7) and update the other one or merge
echo "\n=== Proposed Fix ===\n";

if ($transactions7->count() > 0 && $transactions8->count() == 0) {
    echo "Stock ID 7 has transactions, Stock ID 8 has none.\n";
    echo "Proposed action: Delete Stock ID 8 (duplicate) and keep Stock ID 7.\n";
    
    // Check if we should proceed
    echo "\nExecuting fix...\n";
    
    try {
        // Delete the duplicate stock record (ID 8)
        $adani8->delete();
        echo "✅ Deleted duplicate ADANIPOWER record (ID 8)\n";
        
        // Verify the fix
        $remainingAdani = Stock::where('symbol', 'ADANIPOWER')->get();
        echo "Remaining ADANIPOWER records: {$remainingAdani->count()}\n";
        
        foreach ($remainingAdani as $stock) {
            echo "- ID: {$stock->id}, Symbol: {$stock->symbol}, Price: ₹{$stock->current_price}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
} elseif ($transactions8->count() > 0 && $transactions7->count() == 0) {
    echo "Stock ID 8 has transactions, Stock ID 7 has none.\n";
    echo "Proposed action: Delete Stock ID 7 (duplicate) and keep Stock ID 8.\n";
    
    try {
        $adani7->delete();
        echo "✅ Deleted duplicate ADANIPOWER record (ID 7)\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
} elseif ($transactions7->count() > 0 && $transactions8->count() > 0) {
    echo "Both records have transactions. Manual intervention required.\n";
    echo "Please review and merge manually.\n";
    
} else {
    echo "Neither record has transactions. Keeping the one with better data.\n";
    // Keep the one with current price data
    if ($adani7->current_price > 0 && $adani8->current_price == 0) {
        try {
            $adani8->delete();
            echo "✅ Deleted duplicate ADANIPOWER record (ID 8)\n";
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    } elseif ($adani8->current_price > 0 && $adani7->current_price == 0) {
        try {
            $adani7->delete();
            echo "✅ Deleted duplicate ADANIPOWER record (ID 7)\n";
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Both have same data quality. Keeping ID 7, deleting ID 8.\n";
        try {
            $adani8->delete();
            echo "✅ Deleted duplicate ADANIPOWER record (ID 8)\n";
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Fix Complete ===\n";