<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransaction;

echo "=== Fixing ADANIPOWER Transaction Price ===\n\n";

// Find the buy transaction for ADANIPOWER
$buyTransaction = StockTransaction::where('stock_id', 7)
    ->where('transaction_type', 'buy')
    ->first();

if ($buyTransaction) {
    echo "Found buy transaction:\n";
    echo "- ID: {$buyTransaction->id}\n";
    echo "- Quantity: {$buyTransaction->quantity}\n";
    echo "- Current Price Per Stock: '{$buyTransaction->price_per_stock}'\n";
    echo "- Current Total Amount: '{$buyTransaction->total_amount}'\n";
    echo "- Date: {$buyTransaction->transaction_date}\n\n";
    
    echo "Updating price_per_stock to ₹99.40 and total_amount to ₹10,039.40...\n";
    
    $buyTransaction->price_per_stock = 99.40;
    $buyTransaction->total_amount = 101 * 99.40; // 10,039.40
    $buyTransaction->save();
    
    echo "✅ Price updated successfully!\n\n";
    
    // Verify the update
    $updatedTransaction = StockTransaction::find($buyTransaction->id);
    echo "Verification:\n";
    echo "- New Price Per Stock: ₹{$updatedTransaction->price_per_stock}\n";
    echo "- New Total Amount: ₹{$updatedTransaction->total_amount}\n";
    echo "- Calculated Investment: ₹" . ($updatedTransaction->quantity * $updatedTransaction->price_per_stock) . "\n";
    
} else {
    echo "❌ Buy transaction not found!\n";
}

echo "\n=== Expected Calculation After Fix ===\n";
echo "- Buy: 101 shares × ₹99.40 = ₹10,039.40\n";
echo "- Split: 404 shares × ₹0 = ₹0\n";
echo "- Total Investment: ₹10,039.40\n";
echo "- Current Holding: 505 shares\n";
echo "- Current Price: ₹144.50\n";
echo "- Current Value: 505 × ₹144.50 = ₹72,972.50\n";
echo "- Unrealized P&L: ₹72,972.50 - ₹10,039.40 = ₹62,933.10\n";
echo "\nNote: If expected P&L is ₹22,776, then current price should be ₹65.00\n";