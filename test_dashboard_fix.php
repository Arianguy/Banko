<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\DashboardController;

echo "=== Testing Dashboard Controller Fix ===\n\n";

// Create an instance of the dashboard controller
$dashboardController = new DashboardController();

// Test the equity data calculation
echo "Testing getEquityData method...\n";

try {
    // Use reflection to call the private method
    $reflection = new ReflectionClass($dashboardController);
    $method = $reflection->getMethod('getEquityData');
    $method->setAccessible(true);
    
    $userId = 1; // Assuming user ID 1
    $equityData = $method->invoke($dashboardController, $userId);
    
    echo "Equity Data Results:\n";
    echo "- Total Invested: " . $equityData['total_invested'] . "\n";
    echo "- Current Value: " . $equityData['current_value'] . "\n";
    echo "- Unrealized P/L: " . $equityData['unrealized_pl'] . "\n";
    echo "- Realized P/L: " . $equityData['realized_pl'] . "\n";
    echo "- Total Dividends: " . $equityData['total_dividends'] . "\n";
    
    if ($equityData['unrealized_pl'] > 0) {
        echo "\n✅ SUCCESS: Unrealized P/L is now showing as PROFIT: +" . $equityData['unrealized_pl'] . "\n";
    } else {
        echo "\n❌ ISSUE: Unrealized P/L is still showing as loss: " . $equityData['unrealized_pl'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error testing dashboard controller: " . $e->getMessage() . "\n";
}

echo "\n=== End Test ===\n";