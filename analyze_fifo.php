<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\MutualFund;
use App\Models\MutualFundTransaction;

// Get the user (assuming user ID 1)
$user = User::find(1);
if (!$user) {
    echo 'User not found' . PHP_EOL;
    exit;
}

echo 'User: ' . $user->name . PHP_EOL;

// Get all Canara Robeco transactions
$transactions = MutualFundTransaction::where('user_id', $user->id)
    ->whereHas('mutualFund', function($query) {
        $query->where('scheme_name', 'LIKE', '%Canara Robeco Large Cap%');
    })
    ->orderBy('transaction_date')
    ->orderBy('id')
    ->get();

echo 'Transaction count: ' . $transactions->count() . PHP_EOL;
echo PHP_EOL;

echo 'All Canara Robeco Transactions (chronological order):' . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
foreach ($transactions as $t) {
    echo sprintf(
        'ID: %d | Date: %s | Type: %s | Units: %s | NAV: %s | Net Amount: %s',
        $t->id,
        $t->transaction_date->format('Y-m-d'),
        $t->transaction_type,
        number_format($t->units, 4),
        number_format($t->nav, 4),
        number_format($t->net_amount, 2)
    ) . PHP_EOL;
}

echo PHP_EOL;

// Find the 200 unit redemption transaction
$sellTransaction = $transactions->where('transaction_type', 'redemption')->where('units', 200)->first();
if (!$sellTransaction) {
    echo 'Redemption transaction not found' . PHP_EOL;
    exit;
}

echo 'FIFO Calculation for 200 unit redemption:' . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

// First, let's simulate the FIFO queue considering all transactions
$buyQueue = collect();
$allTransactions = $transactions->sortBy('transaction_date')->values();

echo 'Processing all transactions in chronological order:' . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

foreach ($allTransactions as $transaction) {
    if ($transaction->transaction_type === 'buy') {
        $buyQueue->push([
            'date' => $transaction->transaction_date->format('Y-m-d'),
            'units' => $transaction->units,
            'nav' => $transaction->nav,
            'remaining' => $transaction->units
        ]);
        echo sprintf(
            'BUY: %s | Units: %s | NAV: %s | Queue size: %d',
            $transaction->transaction_date->format('Y-m-d'),
            number_format($transaction->units, 4),
            number_format($transaction->nav, 4),
            $buyQueue->count()
        ) . PHP_EOL;
    } elseif (in_array($transaction->transaction_type, ['sell', 'redemption'])) {
        if ($transaction->id === $sellTransaction->id) {
            // This is our target transaction - stop here and calculate
            break;
        }
        
        echo sprintf(
            'SELL: %s | Units: %s | NAV: %s',
            $transaction->transaction_date->format('Y-m-d'),
            number_format($transaction->units, 4),
            number_format($transaction->nav, 4)
        ) . PHP_EOL;
        
        // Process this sell transaction using FIFO
        $unitsToSell = $transaction->units;
        while ($unitsToSell > 0 && $buyQueue->isNotEmpty()) {
            $buyEntry = $buyQueue->first();
            if ($buyEntry['remaining'] <= $unitsToSell) {
                echo sprintf(
                    '  -> Consuming all %s units from %s buy',
                    number_format($buyEntry['remaining'], 4),
                    $buyEntry['date']
                ) . PHP_EOL;
                $unitsToSell -= $buyEntry['remaining'];
                $buyQueue->shift();
            } else {
                echo sprintf(
                    '  -> Consuming %s units from %s buy (leaving %s)',
                    number_format($unitsToSell, 4),
                    $buyEntry['date'],
                    number_format($buyEntry['remaining'] - $unitsToSell, 4)
                ) . PHP_EOL;
                $buyEntry['remaining'] -= $unitsToSell;
                $buyQueue[0] = $buyEntry;
                $unitsToSell = 0;
            }
        }
    }
}

echo PHP_EOL . 'Remaining buy queue before our 200 unit redemption:' . PHP_EOL;
foreach ($buyQueue as $entry) {
    echo sprintf(
        'Date: %s | Remaining: %s | NAV: %s',
        $entry['date'],
        number_format($entry['remaining'], 4),
        number_format($entry['nav'], 4)
    ) . PHP_EOL;
}

echo PHP_EOL . 'FIFO Processing for 200 unit redemption:' . PHP_EOL;

$unitsToSell = 200;
$totalInvestment = 0;
$totalUnitsSold = 0;

foreach ($buyQueue as $entry) {
    if ($unitsToSell <= 0) break;
    
    $unitsFromThisBuy = min($unitsToSell, $entry['remaining']);
    $investmentFromThisBuy = $unitsFromThisBuy * $entry['nav'];
    
    echo sprintf(
        'Taking %s units from %s buy (NAV: %s) = Investment: %s',
        number_format($unitsFromThisBuy, 4),
        $entry['date'],
        number_format($entry['nav'], 4),
        number_format($investmentFromThisBuy, 2)
    ) . PHP_EOL;
    
    $totalInvestment += $investmentFromThisBuy;
    $totalUnitsSold += $unitsFromThisBuy;
    $unitsToSell -= $unitsFromThisBuy;
}

$averageBuyNAV = $totalUnitsSold > 0 ? $totalInvestment / $totalUnitsSold : 0;

echo PHP_EOL . 'Summary:' . PHP_EOL;
echo sprintf('Total units sold: %s', number_format($totalUnitsSold, 4)) . PHP_EOL;
echo sprintf('Total investment: %s', number_format($totalInvestment, 2)) . PHP_EOL;
echo sprintf('Average buy NAV: %s', number_format($averageBuyNAV, 4)) . PHP_EOL;
echo sprintf('Sell NAV: %s', number_format($sellTransaction->nav, 4)) . PHP_EOL;
echo sprintf('Proceeds: %s', number_format($totalUnitsSold * $sellTransaction->nav, 2)) . PHP_EOL;
echo sprintf('P&L: %s', number_format(($totalUnitsSold * $sellTransaction->nav) - $totalInvestment, 2)) . PHP_EOL;