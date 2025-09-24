<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockTransaction;
use App\Models\FixedDeposit;
use App\Models\BankBalance;
use App\Models\MutualFundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $equityData = $this->getEquityData();
        $fixedDepositData = $this->getFixedDepositData();
        $mutualFundData = $this->getMutualFundData();
        
        return Inertia::render('dashboard', [
            'equity_data' => $equityData,
            'fixed_deposit_data' => $fixedDepositData,
            'bank_balance_data' => $this->getBankBalanceData(),
            'mutual_fund_data' => $mutualFundData
        ]);
    }



    private function getEquityData()
    {
        // Always use current date for calculations
        $calculationDate = Carbon::now();

        // Get all transactions up to the calculation date
        $transactions = StockTransaction::with('stock')
            ->where('user_id', Auth::id())
            ->where('transaction_date', '<=', $calculationDate)
            ->get();

        $totalInvested = 0;
        $realizedPL = 0;
        $unrealizedPL = 0;
        $currentValue = 0;
        $totalDividends = 0;

        // Calculate total invested using FIFO logic to account for sold shares
        $stockTransactionsByStock = $transactions->groupBy('stock_id');
        
        foreach ($stockTransactionsByStock as $stockId => $stockTransactions) {
            // Sort transactions chronologically for FIFO calculation
            $sortedTransactions = $stockTransactions->sortBy(['transaction_date', 'id']);
            
            $buyQueue = collect();
            $stockInvestment = 0;
            
            // Process transactions using FIFO logic
            foreach ($sortedTransactions as $transaction) {
                if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                    $stockInvestment += $transaction->net_amount;
                    
                    // Add to buy queue for FIFO tracking (only for buy transactions, bonus has no cost)
                    if ($transaction->transaction_type === 'buy') {
                        $buyQueue->push([
                            'quantity' => $transaction->quantity,
                            'remaining' => $transaction->quantity,
                            'price_per_share' => $transaction->net_amount / $transaction->quantity,
                            'net_amount' => $transaction->net_amount,
                            'avg_cost_per_share' => $transaction->net_amount / $transaction->quantity
                        ]);
                    } else {
                        // For bonus shares, add to queue with zero cost
                        $buyQueue->push([
                            'quantity' => $transaction->quantity,
                            'remaining' => $transaction->quantity,
                            'price_per_share' => 0,
                            'net_amount' => 0,
                            'avg_cost_per_share' => 0
                        ]);
                    }
                } elseif ($transaction->transaction_type === 'sell') {
                    $remainingToSell = $transaction->quantity;
                    $investmentToReduce = 0;
                    
                    // Use FIFO to calculate investment reduction
                    while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                        $buyEntry = $buyQueue->first();

                        if ($buyEntry['remaining'] <= $remainingToSell) {
                            // Consume entire buy entry
                            $investmentToReduce += $buyEntry['remaining'] * $buyEntry['avg_cost_per_share'];
                            $remainingToSell -= $buyEntry['remaining'];
                            $buyQueue->shift();
                        } else {
                            // Partially consume buy entry
                            $investmentToReduce += $remainingToSell * $buyEntry['avg_cost_per_share'];
                            $buyEntry['remaining'] -= $remainingToSell;
                            $buyQueue[0] = $buyEntry;
                            $remainingToSell = 0;
                        }
                    }

                    $stockInvestment -= $investmentToReduce;
                }
            }
            
            // Only add to total if there are remaining shares
            $totalShares = $buyQueue->sum('remaining');
            if ($totalShares > 0) {
                $totalInvested += $stockInvestment;
            }
        }

        // Calculate realized P&L (sell transactions)
        $sellTransactions = $transactions->where('transaction_type', 'sell');
        foreach ($sellTransactions as $sellTransaction) {
            $realizedPL += $this->calculateRealizedGainLoss(Auth::id(), $sellTransaction);
        }

        // Calculate current holdings and unrealized P&L
        $currentHoldings = $this->calculateCurrentHoldings(Auth::id(), $calculationDate);
        
        foreach ($currentHoldings as $holding) {
            $currentValue += $holding['current_value'];
            $unrealizedPL += $holding['unrealized_gain_loss'];
        }

        // Calculate dividends received up to the calculation date
        $dividendRecords = \App\Models\UserDividendRecord::with('dividendPayment')
            ->where('user_id', Auth::id())
            ->where('status', 'received')
            ->whereHas('dividendPayment', function ($query) use ($calculationDate) {
                $query->where('dividend_date', '<=', $calculationDate);
            })
            ->get();
        
        $totalDividends = $dividendRecords->sum('total_dividend_amount');

        return [
            'total_invested' => round($totalInvested, 2),
            'realized_pl' => round($realizedPL, 2),
            'unrealized_pl' => round($unrealizedPL, 2),
            'current_value' => round($currentValue, 2),
            'total_dividends' => round($totalDividends, 2)
        ];
    }

    private function getFixedDepositData()
    {
        // Always use current date for calculations
        $calculationDate = Carbon::now();

        // Get all fixed deposits that are active (not closed or matured)
        $fixedDeposits = FixedDeposit::where('user_id', Auth::id())
            ->where('closed', false)
            ->where('matured', false)
            ->get();

        $totalPrincipal = $fixedDeposits->sum('principal_amt');
        
        // Calculate unrealized interest based on the calculation date
        $totalUnrealizedInterest = 0;
        $bankWiseData = [];

        foreach ($fixedDeposits as $fd) {
            // Always use the full interest amount instead of pro-rated calculation
            $unrealizedInterest = $fd->Int_amt;
            
            $totalUnrealizedInterest += $unrealizedInterest;
            
            // Group by bank
            if (!isset($bankWiseData[$fd->bank])) {
                $bankWiseData[$fd->bank] = [
                    'principal' => 0,
                    'interest' => 0
                ];
            }
            
            $bankWiseData[$fd->bank]['principal'] += $fd->principal_amt;
            $bankWiseData[$fd->bank]['interest'] += $unrealizedInterest;
        }

        return [
            'total_principal' => round($totalPrincipal, 2),
            'total_unrealized_interest' => round($totalUnrealizedInterest, 2),
            'bank_wise_data' => $bankWiseData
        ];
    }

    private function calculateRealizedGainLoss($userId, $sellTransaction)
    {
        // Get all buy/bonus transactions for this stock up to the sell date
        $buyTransactions = StockTransaction::where('user_id', $userId)
            ->where('stock_id', $sellTransaction->stock_id)
            ->whereIn('transaction_type', ['buy', 'bonus'])
            ->where('transaction_date', '<=', $sellTransaction->transaction_date)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($buyTransactions->isEmpty()) {
            return 0;
        }

        // Build buy queue for FIFO calculation
        $buyQueue = collect();
        foreach ($buyTransactions as $transaction) {
            $buyQueue->push([
                'quantity' => $transaction->quantity,
                'remaining' => $transaction->quantity,
                'cost_per_share' => $transaction->transaction_type === 'buy' ? 
                    $transaction->net_amount / $transaction->quantity : 0,
                'date' => $transaction->transaction_date
            ]);
        }

        // Get all previous sell transactions for this stock to reduce the buy queue
        $previousSells = StockTransaction::where('user_id', $userId)
            ->where('stock_id', $sellTransaction->stock_id)
            ->where('transaction_type', 'sell')
            ->where('transaction_date', '<', $sellTransaction->transaction_date)
            ->orWhere(function($query) use ($sellTransaction) {
                $query->where('transaction_date', '=', $sellTransaction->transaction_date)
                      ->where('id', '<', $sellTransaction->id);
            })
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Process previous sells to reduce buy queue
        foreach ($previousSells as $prevSell) {
            $remainingToSell = $prevSell->quantity;
            
            while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                $buyEntry = $buyQueue->shift();
                
                if ($buyEntry['remaining'] <= $remainingToSell) {
                    $remainingToSell -= $buyEntry['remaining'];
                } else {
                    $buyEntry['remaining'] -= $remainingToSell;
                    $buyQueue->prepend($buyEntry);
                    $remainingToSell = 0;
                }
            }
        }

        // Now calculate realized P&L for current sell transaction using FIFO
        $sellQuantity = $sellTransaction->quantity;
        $sellProceeds = $sellTransaction->net_amount;
        $sellCost = 0;
        $remainingToSell = $sellQuantity;

        while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
            $buyEntry = $buyQueue->shift();
            
            if ($buyEntry['remaining'] <= $remainingToSell) {
                // Use entire buy entry
                $cost = $buyEntry['remaining'] * $buyEntry['cost_per_share'];
                $sellCost += $cost;
                $remainingToSell -= $buyEntry['remaining'];
            } else {
                // Partial use of buy entry
                $cost = $remainingToSell * $buyEntry['cost_per_share'];
                $sellCost += $cost;
                $buyEntry['remaining'] -= $remainingToSell;
                $buyQueue->prepend($buyEntry);
                $remainingToSell = 0;
            }
        }

        $realizedPL = $sellProceeds - $sellCost;
        return $realizedPL;
    }

    private function calculateCurrentHoldings($userId, $endDate)
    {
        $transactions = StockTransaction::with('stock')
            ->where('user_id', $userId)
            ->where('transaction_date', '<=', $endDate)
            ->get()
            ->groupBy('stock.symbol');

        $holdings = [];

        foreach ($transactions as $symbol => $stockTransactions) {
            // Sort transactions chronologically for FIFO calculation
            $sortedTransactions = $stockTransactions->sortBy(['transaction_date', 'id']);
            
            $buyQueue = collect();
            $totalInvestment = 0;
            
            // Process transactions using FIFO logic
            foreach ($sortedTransactions as $transaction) {
                if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                    $totalInvestment += $transaction->net_amount;
                    
                    // Add to buy queue for FIFO tracking
                    if ($transaction->transaction_type === 'buy') {
                        $buyQueue->push([
                            'quantity' => $transaction->quantity,
                            'remaining' => $transaction->quantity,
                            'avg_cost_per_share' => $transaction->net_amount / $transaction->quantity
                        ]);
                    } else {
                        // For bonus shares, add to queue with zero cost
                        $buyQueue->push([
                            'quantity' => $transaction->quantity,
                            'remaining' => $transaction->quantity,
                            'avg_cost_per_share' => 0
                        ]);
                    }
                } elseif ($transaction->transaction_type === 'sell') {
                    $remainingToSell = $transaction->quantity;
                    $investmentToReduce = 0;
                    
                    // Use FIFO to calculate investment reduction
                    while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                        $buyEntry = $buyQueue->first();

                        if ($buyEntry['remaining'] <= $remainingToSell) {
                            // Consume entire buy entry
                            $investmentToReduce += $buyEntry['remaining'] * $buyEntry['avg_cost_per_share'];
                            $remainingToSell -= $buyEntry['remaining'];
                            $buyQueue->shift();
                        } else {
                            // Partially consume buy entry
                            $investmentToReduce += $remainingToSell * $buyEntry['avg_cost_per_share'];
                            $buyEntry['remaining'] -= $remainingToSell;
                            $buyQueue[0] = $buyEntry;
                            $remainingToSell = 0;
                        }
                    }

                    $totalInvestment -= $investmentToReduce;
                }
            }
            
            // Calculate remaining quantity from buy queue
            $totalQuantity = $buyQueue->sum('remaining');

            // Only include if user still holds shares
            if ($totalQuantity > 0) {
                $stock = $stockTransactions->first()->stock;
                $currentPrice = $stock->current_price ?? 0;
                $currentValue = $totalQuantity * $currentPrice;
                $unrealizedGainLoss = $currentValue - $totalInvestment;

                $holdings[] = [
                    'symbol' => $symbol,
                    'quantity' => $totalQuantity,
                    'total_investment' => $totalInvestment,
                    'current_value' => $currentValue,
                    'unrealized_gain_loss' => $unrealizedGainLoss
                ];
            }
        }

        return $holdings;
    }

    private function getBankBalanceData()
    {
        // Always use current date for calculations
        $calculationDate = Carbon::now();
        
        // Get all unique bank-account combinations for the user
        $bankAccounts = BankBalance::where('user_id', Auth::id())
            ->select('bank_id', 'account_number')
            ->distinct()
            ->get();
        
        $bankBalances = [];
        $totalBalance = 0;
        
        foreach ($bankAccounts as $account) {
            // Get the latest balance on or before the calculation date for this bank-account combination
            $balance = BankBalance::with('bank')
                ->where('user_id', Auth::id())
                ->where('bank_id', $account->bank_id)
                ->where('account_number', $account->account_number)
                ->where('update_date', '<=', $calculationDate)
                ->orderBy('update_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            if ($balance) {
                $bankBalances[] = [
                    'bank_name' => $balance->bank->name,
                    'account_number' => $balance->account_number,
                    'balance' => $balance->balance,
                    'update_date' => $balance->update_date->format('Y-m-d')
                ];
                $totalBalance += $balance->balance;
            }
        }
        
        return [
            'bank_balances' => $bankBalances,
            'total_balance' => $totalBalance
        ];
    }

    private function getMutualFundData()
    {
        // Always use current date for calculations
        $calculationDate = Carbon::now();
        
        // Get all mutual fund transactions up to the calculation date
        $transactions = MutualFundTransaction::with('mutualFund')
            ->where('user_id', Auth::id())
            ->where('transaction_date', '<=', $calculationDate)
            ->get();
        
        $totalInvestment = 0;
        $totalCurrentValue = 0;
        $fundWiseData = [];
        
        // Group transactions by mutual fund
        $groupedTransactions = $transactions->groupBy('mutual_fund_id');
        
        foreach ($groupedTransactions as $mutualFundId => $fundTransactions) {
            $mutualFund = $fundTransactions->first()->mutualFund;
            
            // Sort transactions chronologically for FIFO calculation
            $sortedTransactions = $fundTransactions->sortBy(['transaction_date', 'id']);
            
            $buyQueue = collect();
            $totalUnits = 0;
            $fundInvestment = 0;
            
            // Process transactions using FIFO logic
            foreach ($sortedTransactions as $transaction) {
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
                } elseif ($transaction->transaction_type === 'sell' || $transaction->transaction_type === 'redemption') {
                    $totalUnits -= $transaction->units;
                    $remainingToSell = $transaction->units;
                    $investmentToReduce = 0;

                    // Use FIFO to calculate investment reduction
                    while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                        $buyEntry = $buyQueue->first();

                        if ($buyEntry['remaining'] <= $remainingToSell) {
                            // Consume entire buy entry
                            $investmentToReduce += $buyEntry['remaining'] * $buyEntry['avg_cost_per_unit'];
                            $remainingToSell -= $buyEntry['remaining'];
                            $buyQueue->shift();
                        } else {
                            // Partially consume buy entry
                            $investmentToReduce += $remainingToSell * $buyEntry['avg_cost_per_unit'];
                            $buyEntry['remaining'] -= $remainingToSell;
                            $buyQueue[0] = $buyEntry;
                            $remainingToSell = 0;
                        }
                    }

                    $fundInvestment -= $investmentToReduce;
                }
            }
            
            if ($totalUnits > 0) {
                $currentValue = $totalUnits * ($mutualFund->current_nav ?? 0);
                $totalInvestment += $fundInvestment;
                $totalCurrentValue += $currentValue;
                
                $fundWiseData[] = [
                    'scheme_name' => $mutualFund->scheme_name,
                    'fund_house' => $mutualFund->fund_house,
                    'units' => $totalUnits,
                    'investment' => $fundInvestment,
                    'current_value' => $currentValue,
                    'pl' => $currentValue - $fundInvestment
                ];
            }
        }
        
        return [
            'total_investment' => round($totalInvestment, 2),
            'total_current_value' => round($totalCurrentValue, 2),
            'total_pl' => round($totalCurrentValue - $totalInvestment, 2),
            'fund_wise_data' => $fundWiseData
        ];
    }
}