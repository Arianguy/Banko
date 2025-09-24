<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockTransaction;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;

echo "=== Testing Dashboard Fix ===\n\n";

// Check users
$userCount = \App\Models\User::count();
echo "Users in database: {$userCount}\n";

if ($userCount > 0) {
    $user = \App\Models\User::first();
    echo "First user: {$user->email}\n";
    
    // Manually set the authenticated user
    Auth::login($user);
    
    echo "\nTesting with user ID: {$user->id}\n";
    
    // Get transactions for this user
    $transactions = StockTransaction::where('user_id', $user->id)->with('stock')->get();
    echo "Total transactions: " . $transactions->count() . "\n";
    
    $splitTransactions = $transactions->where('transaction_type', 'split');
    echo "Split transactions: " . $splitTransactions->count() . "\n";
    
    if ($splitTransactions->count() > 0) {
        foreach ($splitTransactions as $split) {
            echo "- Split: {$split->stock->symbol}, {$split->quantity} shares\n";
        }
    }
    
    // Now test the dashboard controller
    $dashboardController = new \App\Http\Controllers\DashboardController();
    
    try {
        $reflection = new ReflectionClass($dashboardController);
        $method = $reflection->getMethod('getEquityData');
        $method->setAccessible(true);
        
        $equityData = $method->invoke($dashboardController);
        
        echo "\nDashboard Results:\n";
        echo "- Total Invested: " . $equityData['total_invested'] . "\n";
        echo "- Current Value: " . $equityData['current_value'] . "\n";
        echo "- Unrealized P/L: " . $equityData['unrealized_pl'] . "\n";
        
        if ($equityData['unrealized_pl'] > 0) {
            echo "\n✅ SUCCESS: Unrealized P/L is now PROFIT: +" . $equityData['unrealized_pl'] . "\n";
        } else {
            echo "\n❌ ISSUE: Unrealized P/L is still showing as loss: " . $equityData['unrealized_pl'] . "\n";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "No users found in database.\n";
}

echo "\n=== End Test ===\n";