<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use App\Http\Controllers\MutualFundController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get the first user (assuming there's at least one user)
$user = User::first();
if (!$user) {
    echo "No users found in the database.\n";
    exit(1);
}

// Set the authenticated user
Auth::login($user);

// Create controller instance
$controller = new MutualFundController();

// Call getSoldHistory method
$response = $controller->getSoldHistory();

// Get the response data
$soldHistory = $response->getData(true);

echo "Sold History Results:\n";
echo "====================\n";

if (empty($soldHistory)) {
    echo "No sold history found.\n";
} else {
    foreach ($soldHistory as $index => $entry) {
        echo "Entry " . ($index + 1) . ":\n";
        echo "  Fund: " . $entry['scheme_name'] . "\n";
        echo "  Units Sold: " . $entry['units_sold'] . "\n";
        echo "  Avg Buy NAV: " . $entry['avg_buy_nav'] . "\n";
        echo "  Investment: " . $entry['total_investment'] . "\n";
        echo "  Proceeds: " . $entry['total_proceeds'] . "\n";
        echo "  Realized P&L: " . $entry['realized_pl'] . "\n";
        echo "  Sell Date: " . $entry['sell_date'] . "\n";
        echo "  -------------------------\n";
    }
}

echo "\nTotal entries: " . count($soldHistory) . "\n";