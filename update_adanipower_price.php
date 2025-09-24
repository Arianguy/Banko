<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;

echo "=== Updating ADANIPOWER Current Price ===\n\n";

$stock = Stock::where('symbol', 'ADANIPOWER')->first();

echo "Current price in database: ₹{$stock->current_price}\n";
echo "Expected price from screenshot: ₹144.50\n";
echo "Expected price for P&L ₹22,776: ₹65.00\n\n";

echo "Updating to ₹144.50 (from screenshot)...\n";

$stock->current_price = 144.50;
$stock->save();

echo "✅ Price updated successfully!\n\n";

// Recalculate with new price
$currentHolding = 505; // 101 buy + 404 split
$netInvestment = 10039.40;
$newCurrentValue = $currentHolding * 144.50;
$newUnrealizedPL = $newCurrentValue - $netInvestment;

echo "=== New Calculation ===\n";
echo "Current Holding: {$currentHolding} shares\n";
echo "Net Investment: ₹{$netInvestment}\n";
echo "New Current Value: {$currentHolding} × ₹144.50 = ₹{$newCurrentValue}\n";
echo "New Unrealized P&L: ₹{$newCurrentValue} - ₹{$netInvestment} = ₹{$newUnrealizedPL}\n";

echo "\nExpected P&L: ₹22,776\n";
echo "Actual P&L: ₹{$newUnrealizedPL}\n";
echo "Still need to adjust price to: ₹" . ((22776 + $netInvestment) / $currentHolding) . "\n";