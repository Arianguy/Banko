<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Collection;
use App\Models\StockTransaction;
use App\Models\Stock;

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== EQUITY INVESTMENT CALCULATION DEBUG ===" . PHP_EOL . PHP_EOL;

// Get all stock transactions for user ID 1 (adjust as needed)
$userId = 1;
$transactions = StockTransaction::with('stock')
    ->where('user_id', $userId)
    ->orderBy('transaction_date', 'asc')
    ->orderBy('id', 'asc')
    ->get();

echo "Total transactions found: " . $transactions->count() . PHP_EOL . PHP_EOL;

// Group transactions by stock
$stockTransactionsByStock = $transactions->groupBy('stock_id');
$totalInvestmentAcrossAllStocks = 0;

foreach ($stockTransactionsByStock as $stockId => $stockTransactions) {
    $stock = $stockTransactions->first()->stock;
    echo "=== PROCESSING STOCK: " . $stock->symbol . " (" . $stock->name . ") ===" . PHP_EOL;
    
    // Sort transactions chronologically for FIFO calculation
    $sortedTransactions = $stockTransactions->sortBy(['transaction_date', 'id']);
    
    $buyQueue = collect();
    $stockInvestment = 0;
    
    echo "Processing " . $sortedTransactions->count() . " transactions chronologically:" . PHP_EOL;
    
    // Process transactions using FIFO logic
    foreach ($sortedTransactions as $transaction) {
        echo "  " . $transaction->transaction_date->format('Y-m-d') . " - " . 
             ucfirst($transaction->transaction_type) . " " . 
             number_format($transaction->quantity, 0) . " shares at " . 
             number_format($transaction->price_per_stock, 2) . " = " . 
             number_format($transaction->net_amount, 2) . PHP_EOL;
        
        if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
            $stockInvestment += $transaction->net_amount;
            
            // Add to buy queue for FIFO tracking
            if ($transaction->transaction_type === 'buy') {
                $buyQueue->push([
                    'quantity' => $transaction->quantity,
                    'remaining' => $transaction->quantity,
                    'price_per_share' => $transaction->net_amount / $transaction->quantity,
                    'net_amount' => $transaction->net_amount,
                    'avg_cost_per_share' => $transaction->net_amount / $transaction->quantity
                ]);
                echo "    -> Added to buy queue. Stock investment: " . number_format($stockInvestment, 2) . PHP_EOL;
            } else {
                // For bonus shares, add to queue with zero cost
                $buyQueue->push([
                    'quantity' => $transaction->quantity,
                    'remaining' => $transaction->quantity,
                    'price_per_share' => 0,
                    'net_amount' => 0,
                    'avg_cost_per_share' => 0
                ]);
                echo "    -> Added bonus shares to buy queue (zero cost)" . PHP_EOL;
            }
            
        } elseif ($transaction->transaction_type === 'sell') {
            $remainingToSell = $transaction->quantity;
            $investmentToReduce = 0;
            
            echo "  -> Buy queue before processing: " . $buyQueue->count() . " entries" . PHP_EOL;
            
            // Use FIFO to calculate investment reduction
            while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                $buyEntry = $buyQueue->first();
                
                echo "    -> Buy entry: " . number_format($buyEntry['remaining'], 0) . " shares at avg cost " . number_format($buyEntry['avg_cost_per_share'], 4) . PHP_EOL;

                if ($buyEntry['remaining'] <= $remainingToSell) {
                    // Consume entire buy entry
                    $reductionAmount = $buyEntry['remaining'] * $buyEntry['avg_cost_per_share'];
                    $investmentToReduce += $reductionAmount;
                    $remainingToSell -= $buyEntry['remaining'];
                    $buyQueue->shift();
                    
                    echo "    -> Consumed entire entry. Reduction: " . number_format($reductionAmount, 2) . PHP_EOL;
                } else {
                    // Partially consume buy entry
                    $reductionAmount = $remainingToSell * $buyEntry['avg_cost_per_share'];
                    $investmentToReduce += $reductionAmount;
                    $buyEntry['remaining'] -= $remainingToSell;
                    $buyQueue[0] = $buyEntry;
                    $remainingToSell = 0;
                    
                    echo "    -> Partially consumed entry. Reduction: " . number_format($reductionAmount, 2) . PHP_EOL;
                }
            }

            $stockInvestment -= $investmentToReduce;
            echo "  -> Total investment reduction: " . number_format($investmentToReduce, 2) . PHP_EOL;
            echo "  -> Stock investment after reduction: " . number_format($stockInvestment, 2) . PHP_EOL;
        }
        
        echo "  -> Current total shares: " . number_format($buyQueue->sum('remaining'), 0) . PHP_EOL;
        echo PHP_EOL;
    }
    
    // Only add to total if there are remaining shares
    $totalShares = $buyQueue->sum('remaining');
    echo "Final stock summary:" . PHP_EOL;
    echo "  Total shares: " . number_format($totalShares, 0) . PHP_EOL;
    echo "  Stock investment: " . number_format($stockInvestment, 2) . PHP_EOL;
    echo "  Buy queue remaining: " . $buyQueue->count() . " entries" . PHP_EOL;
    
    if ($totalShares > 0) {
        $totalInvestmentAcrossAllStocks += $stockInvestment;
        echo "  -> Adding to total investment" . PHP_EOL;
    } else {
        echo "  -> Not adding to total (no remaining shares)" . PHP_EOL;
    }
    
    echo PHP_EOL . "===========================================" . PHP_EOL . PHP_EOL;
}

echo "=== FINAL SUMMARY ===" . PHP_EOL;
echo "Total Investment Across All Stocks: " . number_format($totalInvestmentAcrossAllStocks, 2) . PHP_EOL;