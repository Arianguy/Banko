<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MutualFundTransaction;
use Carbon\Carbon;

// Get user ID 1 (adjust if needed)
$userId = 1;

echo "=== Debugging Mutual Fund Investment Calculation ===" . PHP_EOL . PHP_EOL;

// Get all mutual fund transactions
$transactions = MutualFundTransaction::with('mutualFund')
    ->where('user_id', $userId)
    ->orderBy('transaction_date', 'asc')
    ->orderBy('id', 'asc')
    ->get();

echo "Total transactions found: " . $transactions->count() . PHP_EOL . PHP_EOL;

// Group by fund
$groupedTransactions = $transactions->groupBy('mutual_fund_id');

$totalInvestmentAcrossAllFunds = 0;

foreach ($groupedTransactions as $mutualFundId => $fundTransactions) {
    $mutualFund = $fundTransactions->first()->mutualFund;
    
    echo "=== Fund: " . $mutualFund->scheme_name . " ===" . PHP_EOL;
    
    // Sort transactions chronologically for FIFO calculation
    $sortedTransactions = $fundTransactions->sortBy(['transaction_date', 'id']);
    
    $buyQueue = collect();
    $totalUnits = 0;
    $fundInvestment = 0;
    
    echo "Processing transactions chronologically:" . PHP_EOL;
    
    // Process transactions using FIFO logic
    foreach ($sortedTransactions as $transaction) {
        echo sprintf(
            "Date: %s | Type: %s | Units: %s | NAV: %s | Amount: %s",
            $transaction->transaction_date->format('Y-m-d'),
            $transaction->transaction_type,
            number_format($transaction->units, 3),
            number_format($transaction->nav, 4),
            number_format($transaction->net_amount, 2)
        ) . PHP_EOL;
        
        if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'sip') {
            $totalUnits += $transaction->units;
            $fundInvestment += $transaction->net_amount;
            
            // Add to buy queue for FIFO tracking
            $buyQueue->push([
                'units' => $transaction->units,
                'remaining' => $transaction->units,
                'nav' => $transaction->nav,
                'net_amount' => $transaction->net_amount,
                'avg_cost_per_unit' => $transaction->net_amount / $transaction->units
            ]);
            
            echo "  -> Added to buy queue. Fund investment now: " . number_format($fundInvestment, 2) . PHP_EOL;
            
        } elseif ($transaction->transaction_type === 'sell' || $transaction->transaction_type === 'redemption') {
            $totalUnits -= $transaction->units;
            $remainingToSell = $transaction->units;
            $investmentToReduce = 0;
            
            echo "  -> Processing sell/redemption of " . number_format($transaction->units, 3) . " units" . PHP_EOL;
            echo "  -> Buy queue before processing: " . $buyQueue->count() . " entries" . PHP_EOL;
            
            // Use FIFO to calculate investment reduction
            while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                $buyEntry = $buyQueue->first();
                
                echo "    -> Buy entry: " . number_format($buyEntry['remaining'], 3) . " units at avg cost " . number_format($buyEntry['avg_cost_per_unit'], 4) . PHP_EOL;

                if ($buyEntry['remaining'] <= $remainingToSell) {
                    // Consume entire buy entry
                    $reductionAmount = $buyEntry['remaining'] * $buyEntry['avg_cost_per_unit'];
                    $investmentToReduce += $reductionAmount;
                    $remainingToSell -= $buyEntry['remaining'];
                    $buyQueue->shift();
                    
                    echo "    -> Consumed entire entry. Reduction: " . number_format($reductionAmount, 2) . PHP_EOL;
                } else {
                    // Partially consume buy entry
                    $reductionAmount = $remainingToSell * $buyEntry['avg_cost_per_unit'];
                    $investmentToReduce += $reductionAmount;
                    $buyEntry['remaining'] -= $remainingToSell;
                    $buyQueue[0] = $buyEntry;
                    $remainingToSell = 0;
                    
                    echo "    -> Partially consumed entry. Reduction: " . number_format($reductionAmount, 2) . PHP_EOL;
                }
            }

            $fundInvestment -= $investmentToReduce;
            echo "  -> Total investment reduction: " . number_format($investmentToReduce, 2) . PHP_EOL;
            echo "  -> Fund investment after reduction: " . number_format($fundInvestment, 2) . PHP_EOL;
        }
        
        echo "  -> Current total units: " . number_format($totalUnits, 3) . PHP_EOL;
        echo PHP_EOL;
    }
    
    echo "Final fund summary:" . PHP_EOL;
    echo "  Total units: " . number_format($totalUnits, 3) . PHP_EOL;
    echo "  Fund investment: " . number_format($fundInvestment, 2) . PHP_EOL;
    echo "  Buy queue remaining: " . $buyQueue->count() . " entries" . PHP_EOL;
    
    if ($totalUnits > 0) {
        $totalInvestmentAcrossAllFunds += $fundInvestment;
        echo "  -> Adding to total investment" . PHP_EOL;
    } else {
        echo "  -> Not adding to total (no remaining units)" . PHP_EOL;
    }
    
    echo PHP_EOL . "===========================================" . PHP_EOL . PHP_EOL;
}

echo "=== FINAL SUMMARY ===" . PHP_EOL;
echo "Total Investment Across All Funds: " . number_format($totalInvestmentAcrossAllFunds, 2) . PHP_EOL;