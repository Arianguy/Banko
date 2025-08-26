<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockTransaction;
use App\Services\DividendService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class EquityHoldingController extends Controller
{
    private $dividendService;

    public function __construct(DividendService $dividendService)
    {
        $this->dividendService = $dividendService;
    }

    public function index()
    {
        $user = Auth::user();

        // Get all stock holdings for the user
        $holdings = $this->calculateHoldings($user->id);

        // Calculate additional metrics
        $portfolioMetrics = $this->calculatePortfolioMetrics($holdings);

        // Get dividend summary for the user
        $dividendSummary = $this->dividendService->getUserDividendSummary($user->id);

        // Get STCG/LTCG summary
        $capitalGainsSummary = $this->calculateCapitalGainsSummary($user->id);

        return Inertia::render('EquityHolding/Index', [
            'holdings' => $holdings,
            'portfolioMetrics' => $portfolioMetrics,
            'dividendSummary' => $dividendSummary,
            'capitalGainsSummary' => $capitalGainsSummary,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_type' => 'required|string|in:buy,bonus,sell',
            'stock_name' => 'required|string',
            'exchange' => 'required|string|in:NSE,BSE',
            'date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'price_per_stock' => 'required|numeric|min:0',
            'broker' => 'nullable|string',
            'total_charges' => 'required|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Create or find stock
        $stock = Stock::firstOrCreate([
            'symbol' => strtoupper($validated['stock_name']),
            'exchange' => $validated['exchange']
        ], [
            'name' => $validated['stock_name'],
        ]);

        // Additional validation for sell transactions
        if ($validated['transaction_type'] === 'sell') {
            $currentHolding = $this->calculateCurrentHolding(Auth::id(), $stock->id);
            if ($currentHolding < $validated['quantity']) {
                return redirect()->back()->withErrors([
                    'quantity' => "Cannot sell {$validated['quantity']} shares. You only own {$currentHolding} shares."
                ])->withInput();
            }
        }

        // Calculate amounts
        $totalAmount = $validated['quantity'] * $validated['price_per_stock'];

        // For sell transactions, net_amount is what user receives (total - charges)
        // For buy transactions, net_amount is what user pays (total + charges)
        if ($validated['transaction_type'] === 'sell') {
            $brokerage = $validated['total_charges'] - ($totalAmount - $validated['net_amount']);
        } else {
            $brokerage = $totalAmount - $validated['net_amount'] - $validated['total_charges'];
        }

        // Create transaction
        StockTransaction::create([
            'user_id' => Auth::id(),
            'stock_id' => $stock->id,
            'transaction_type' => $validated['transaction_type'],
            'quantity' => $validated['quantity'],
            'price_per_stock' => $validated['price_per_stock'],
            'total_amount' => $totalAmount,
            'transaction_date' => $validated['date'],
            'exchange' => $validated['exchange'],
            'broker' => $validated['broker'],
            'brokerage' => $brokerage,
            'total_charges' => $validated['total_charges'],
            'net_amount' => $validated['net_amount'],
            'notes' => $validated['notes'],
        ]);

        $action = $validated['transaction_type'] === 'sell' ? 'sold' : 'added';
        return redirect()->route('equity-holding.index')->with('success', "Successfully {$action} {$validated['quantity']} shares of {$stock->symbol}!");
    }

    public function getTransactions($stockId)
    {
        $transactions = StockTransaction::with('stock')
            ->where('user_id', Auth::id())
            ->where('stock_id', $stockId)
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json($transactions);
    }

    public function searchStocks(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $stocks = Stock::where('symbol', 'LIKE', "%{$query}%")
            ->orWhere('name', 'LIKE', "%{$query}%")
            ->orderBy('symbol')
            ->limit(10)
            ->get(['id', 'symbol', 'name', 'current_price', 'exchange']);

        return response()->json($stocks);
    }

    public function getHoldingInfo($stockId)
    {
        $user = Auth::user();
        $stock = Stock::findOrFail($stockId);

        $currentQuantity = $this->calculateCurrentHolding($user->id, $stockId);

        if ($currentQuantity <= 0) {
            return response()->json(['error' => 'No holdings found for this stock'], 404);
        }

        // Get average purchase price for reference
        $buyTransactions = StockTransaction::where('user_id', $user->id)
            ->where('stock_id', $stockId)
            ->where('transaction_type', 'buy')
            ->get();

        $totalInvestment = $buyTransactions->sum('net_amount');
        $totalBoughtQuantity = $buyTransactions->sum('quantity');
        $avgPrice = $totalBoughtQuantity > 0 ? $totalInvestment / $totalBoughtQuantity : 0;

        return response()->json([
            'stock' => $stock,
            'current_quantity' => $currentQuantity,
            'avg_purchase_price' => round($avgPrice, 2),
            'current_price' => $stock->current_price,
            'total_investment' => round($totalInvestment, 2),
        ]);
    }

    public function getSoldHistory()
    {
        $user = Auth::user();

        // Get all transactions to calculate final holdings
        $allTransactions = StockTransaction::with('stock')
            ->where('user_id', $user->id)
            ->get();

        // Calculate final holdings for each stock using FIFO logic
        $stockHoldings = [];
        $stockTransactionsByStock = $allTransactions->groupBy('stock_id');

        foreach ($stockTransactionsByStock as $stockId => $transactions) {
            $buyQueue = collect();

            // Sort transactions chronologically
            $sortedTransactions = $transactions->sortBy(['transaction_date', 'id']);

            // Process transactions using FIFO
            foreach ($sortedTransactions as $transaction) {
                if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                    $buyQueue->push([
                        'quantity' => $transaction->quantity,
                        'remaining' => $transaction->quantity
                    ]);
                } elseif ($transaction->transaction_type === 'sell') {
                    $remainingToSell = $transaction->quantity;

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
            }

            // Calculate final holdings
            $stockHoldings[$stockId] = $buyQueue->sum('remaining');
        }

        // Find stocks that have any sell transactions (regardless of current holdings)
        $stocksWithSells = $allTransactions
            ->where('transaction_type', 'sell')
            ->pluck('stock_id')
            ->unique()
            ->toArray();

        if (empty($stocksWithSells)) {
            return response()->json([]);
        }

        // Get transactions for stocks that have sell transactions
        $soldStockTransactions = StockTransaction::with('stock')
            ->where('user_id', $user->id)
            ->whereIn('stock_id', $stocksWithSells)
            ->orderBy('transaction_date', 'desc') // Latest on top
            ->get();

        // Group transactions by stock
        $groupedByStock = $soldStockTransactions->groupBy('stock_id');
        $soldHistory = [];

        foreach ($groupedByStock as $stockId => $stockTransactions) {
            $stockInfo = $stockTransactions->first()->stock;
            $processedTransactions = [];
            $totalInvestment = 0;
            $totalProceeds = 0;
            $totalRealizedPL = 0;

            foreach ($stockTransactions as $transaction) {
                $transactionType = ucfirst($transaction->transaction_type);

                // Calculate total amount and charges consistently
                $totalAmount = $transaction->quantity * $transaction->price_per_stock;
                $charges = abs($totalAmount - $transaction->net_amount); // Always positive

                // Calculate realized gain/loss for sell transactions
                $realizedGainLoss = null;
                if ($transaction->transaction_type === 'sell') {
                    $realizedGainLoss = $this->calculateRealizedGainLoss($user->id, $transaction);
                    $totalProceeds += $transaction->net_amount;
                    $totalRealizedPL += $realizedGainLoss;
                } elseif ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                    $totalInvestment += $transaction->net_amount;
                }

                $processedTransactions[] = [
                    'date' => $transaction->transaction_date,
                    'type' => $transactionType,
                    'quantity' => $transaction->quantity,
                    'unit_price' => $transaction->price_per_stock,
                    'total_amount' => round($totalAmount, 2),
                    'charges' => round($charges, 2),
                    'net_amount' => round($transaction->net_amount, 2),
                    'realized_gain_loss' => $realizedGainLoss ? round($realizedGainLoss, 2) : null,
                    'broker' => $transaction->broker,
                    'notes' => $transaction->notes,
                ];
            }

            $soldHistory[] = [
                'stock_id' => $stockId,
                'stock_symbol' => $stockInfo->symbol,
                'stock_name' => $stockInfo->name,
                'sector' => $stockInfo->sector ?? 'Unknown',
                'transactions' => $processedTransactions,
                'total_investment' => round($totalInvestment, 2),
                'total_proceeds' => round($totalProceeds, 2),
                'total_realized_pl' => round($totalRealizedPL, 2),
                'total_transactions' => count($processedTransactions),
            ];
        }

        return response()->json($soldHistory);
    }

    private function calculateRealizedGainLoss($userId, $sellTransaction)
    {
        // Get all buy transactions for this stock up to the sell date
        $buyTransactions = StockTransaction::where('user_id', $userId)
            ->where('stock_id', $sellTransaction->stock_id)
            ->where('transaction_type', 'buy')
            ->where('transaction_date', '<=', $sellTransaction->transaction_date)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($buyTransactions->isEmpty()) {
            return 0;
        }

        // Calculate average buy price
        $totalInvestment = $buyTransactions->sum('net_amount');
        $totalQuantity = $buyTransactions->sum('quantity');
        $avgBuyPrice = $totalQuantity > 0 ? $totalInvestment / $totalQuantity : 0;

        // Calculate realized P&L
        $soldInvestment = $avgBuyPrice * $sellTransaction->quantity;
        $realizedPL = $sellTransaction->net_amount - $soldInvestment;

        return $realizedPL;
    }

    private function calculateCapitalGainsSummary($userId)
    {
        // Get all sell transactions to calculate STCG/LTCG
        $sellTransactions = StockTransaction::with('stock')
            ->where('user_id', $userId)
            ->where('transaction_type', 'sell')
            ->get();

        $stcgTotal = 0;
        $ltcgTotal = 0;

        foreach ($sellTransactions as $sellTransaction) {
            // Calculate days held for each transaction
            $buyTransactions = StockTransaction::where('user_id', $userId)
                ->where('stock_id', $sellTransaction->stock_id)
                ->where('transaction_type', 'buy')
                ->where('transaction_date', '<=', $sellTransaction->transaction_date)
                ->get();

            $firstBuyDate = $buyTransactions->min('transaction_date');
            $daysHeld = 0;

            if ($firstBuyDate) {
                $buyDate = \Carbon\Carbon::parse($firstBuyDate);
                $sellDate = \Carbon\Carbon::parse($sellTransaction->transaction_date);

                if ($buyDate->isSameDay($sellDate)) {
                    $daysHeld = 0;
                } else {
                    $daysHeld = $buyDate->diffInDays($sellDate);
                }
            }

            // Calculate realized P&L
            $totalBuyInvestment = $buyTransactions->sum('net_amount');
            $totalBuyQuantity = $buyTransactions->sum('quantity');
            $avgBuyPrice = $totalBuyQuantity > 0 ? $totalBuyInvestment / $totalBuyQuantity : 0;
            $soldQuantityInvestment = $avgBuyPrice * $sellTransaction->quantity;
            $realizedPL = $sellTransaction->net_amount - $soldQuantityInvestment;

            // Add to STCG or LTCG based on holding period
            if ($daysHeld <= 365) {
                $stcgTotal += $realizedPL;
            } else {
                $ltcgTotal += $realizedPL;
            }
        }

        return [
            'stcg_total' => round($stcgTotal, 2),
            'ltcg_total' => round($ltcgTotal, 2),
            'total_capital_gains' => round($stcgTotal + $ltcgTotal, 2),
        ];
    }

    private function getDividendsForSoldStock($userId, $stockId, $buyDate, $sellDate)
    {
        if (!$buyDate || !$sellDate) {
            return 0;
        }

        try {
            // Get dividend records for this user and stock during the holding period
            $dividendRecords = \App\Models\UserDividendRecord::with('dividendPayment')
                ->where('user_id', $userId)
                ->where('stock_id', $stockId)
                ->where('status', 'received')
                ->whereHas('dividendPayment', function ($query) use ($buyDate, $sellDate) {
                    $query->where('ex_dividend_date', '>=', $buyDate)
                        ->where('ex_dividend_date', '<=', $sellDate);
                })
                ->get();

            return $dividendRecords->sum('total_dividend_amount');
        } catch (\Exception $e) {
            // Return 0 if there's any error with dividend calculation
            \Illuminate\Support\Facades\Log::warning("Error calculating dividends for sold stock: " . $e->getMessage());
            return 0;
        }
    }

    public function syncPrices(Request $request)
    {
        try {
            $stockIds = $request->input('stock_ids');

            if ($stockIds) {
                // Update prices for specific stocks only
                $command = 'stock-prices:update';
                foreach ($stockIds as $stockId) {
                    Artisan::call($command, ['--stock' => $stockId]);
                }
                $output = "Successfully updated prices for user's portfolio stocks";
            } else {
                // Run the stock prices update command for all stocks
                Artisan::call('stock-prices:update');
                $output = Artisan::output();
            }

            // Parse the output to get success/failure counts (for all stocks sync)
            if (!$stockIds) {
                preg_match('/Successfully updated: (\d+) stocks/', $output, $successMatches);
                preg_match('/Failed to update: (\d+) stocks/', $output, $failMatches);

                $successCount = $successMatches[1] ?? 0;
                $failCount = $failMatches[1] ?? 0;

                if ($successCount > 0) {
                    $message = "✅ Successfully updated {$successCount} stock prices using multi-API system";
                    if ($failCount > 0) {
                        $message .= " (❌ {$failCount} failed)";
                    }
                } else {
                    $message = "❌ Failed to update any stock prices. All APIs may be unavailable.";
                }
            } else {
                $message = "✅ Successfully updated prices for your portfolio stocks";
            }

            // Check if this is an AJAX request (from frontend)
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->route('equity-holding.index')->with('success', $message);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to sync prices: ' . $e->getMessage()], 500);
            }
            return redirect()->route('equity-holding.index')->with('error', 'Failed to sync prices: ' . $e->getMessage());
        }
    }

    public function updateTransaction(Request $request, $transactionId)
    {
        $validated = $request->validate([
            'transaction_type' => 'required|string|in:buy,bonus,sell',
            'date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'price_per_stock' => 'required|numeric|min:0',
            'broker' => 'nullable|string',
            'total_charges' => 'required|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $transaction = StockTransaction::where('user_id', Auth::id())
            ->findOrFail($transactionId);

        // Calculate amounts
        $totalAmount = $validated['quantity'] * $validated['price_per_stock'];
        $brokerage = $totalAmount - $validated['net_amount'] - $validated['total_charges'];

        // Update transaction
        $transaction->update([
            'transaction_type' => $validated['transaction_type'],
            'quantity' => $validated['quantity'],
            'price_per_stock' => $validated['price_per_stock'],
            'total_amount' => $totalAmount,
            'transaction_date' => $validated['date'],
            'broker' => $validated['broker'],
            'brokerage' => $brokerage,
            'total_charges' => $validated['total_charges'],
            'net_amount' => $validated['net_amount'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('equity-holding.index')->with('success', 'Transaction updated successfully!');
    }

    private function calculateCurrentHolding($userId, $stockId)
    {
        $transactions = StockTransaction::where('user_id', $userId)
            ->where('stock_id', $stockId)
            ->orderBy('transaction_date')
            ->get();

        // Find the last point where quantity became zero (complete sell-off)
        $runningQuantity = 0;
        $lastZeroPoint = null;
        $segmentStartIndex = 0;

        foreach ($transactions as $index => $transaction) {
            if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                $runningQuantity += $transaction->quantity;
            } elseif ($transaction->transaction_type === 'sell') {
                $runningQuantity -= $transaction->quantity;
            }

            // If quantity becomes zero, mark this as a potential segment boundary
            if ($runningQuantity === 0) {
                $lastZeroPoint = $index;
                $segmentStartIndex = $index + 1;
            }
        }

        // Calculate current holdings based on transactions after the last complete sell-off
        $currentSegmentTransactions = $transactions->slice($segmentStartIndex);
        $currentSegmentQuantity = 0;

        foreach ($currentSegmentTransactions as $transaction) {
            if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                $currentSegmentQuantity += $transaction->quantity;
            } elseif ($transaction->transaction_type === 'sell') {
                $currentSegmentQuantity -= $transaction->quantity;
            }
        }

        return $currentSegmentQuantity;
    }

    private function calculateHoldings($userId)
    {
        $transactions = StockTransaction::with('stock')
            ->where('user_id', $userId)
            ->get()
            ->groupBy('stock.symbol'); // Group by symbol instead of stock_id

        $tempHoldings = [];
        $totalPortfolioValue = 0;

        foreach ($transactions as $symbol => $stockTransactions) {
            $totalQuantity = 0;
            $totalInvestment = 0;
            $totalSellProceeds = 0;
            $realizedPL = 0;

            $buyTransactions = $stockTransactions->where('transaction_type', 'buy');
            $sellTransactions = $stockTransactions->where('transaction_type', 'sell');
            $bonusTransactions = $stockTransactions->where('transaction_type', 'bonus');

            // Get the stock record for this symbol with the most recent price update
            $representativeStock = Stock::where('symbol', $symbol)
                ->orderBy('updated_at', 'desc')
                ->first() ?: $stockTransactions->first()->stock;

            // Sort all transactions by date to use FIFO for current holdings calculation
            $allTransactionsSorted = $stockTransactions->sortBy(['transaction_date', 'id']);

            // FIFO matching to find which buy/bonus transactions contribute to current holdings
            $buyQueue = collect();
            $currentHoldingTransactions = collect();

            // Process all transactions chronologically
            foreach ($allTransactionsSorted as $transaction) {
                if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                    // Add to buy queue
                    $buyQueue->push([
                        'transaction' => $transaction,
                        'quantity' => $transaction->quantity,
                        'remaining' => $transaction->quantity
                    ]);
                } elseif ($transaction->transaction_type === 'sell') {
                    $remainingToSell = $transaction->quantity;

                    // Match against buy queue using FIFO
                    while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                        $buyEntry = $buyQueue->shift(); // Remove first element

                        if ($buyEntry['remaining'] <= $remainingToSell) {
                            // This buy transaction is completely consumed
                            $remainingToSell -= $buyEntry['remaining'];
                            // Don't put it back - it's fully consumed
                        } else {
                            // Partial consumption of this buy transaction
                            $buyEntry['remaining'] -= $remainingToSell;
                            $buyQueue->prepend($buyEntry); // Put back the partially consumed entry
                            $remainingToSell = 0;
                        }
                    }
                }
            }

            // Extract transactions that still contribute to current holdings
            foreach ($buyQueue as $buyEntry) {
                if ($buyEntry['remaining'] > 0) {
                    $currentHoldingTransactions->push($buyEntry);
                }
            }

            // Calculate total current holdings quantity
            $currentHoldingsQuantity = $buyQueue->sum('remaining');

            // Calculate overall metrics including all historical transactions for reporting
            foreach ($buyTransactions as $transaction) {
                $totalQuantity += $transaction->quantity;
                $totalInvestment += $transaction->net_amount;
            }

            foreach ($bonusTransactions as $transaction) {
                $totalQuantity += $transaction->quantity;
                // Bonus shares don't add to investment cost
            }

            foreach ($sellTransactions as $transaction) {
                $totalQuantity -= $transaction->quantity;
                $totalSellProceeds += $transaction->net_amount;

                // Calculate realized P&L using proper FIFO logic (this is just for historical display)
                $realizedPL += $this->calculateRealizedGainLoss($userId, $transaction);
            }

            // Only show holdings where user currently owns shares
            if ($currentHoldingsQuantity > 0) {
                // Calculate investment for current holdings only
                $currentHoldingsInvestment = $currentHoldingTransactions->sum(function ($buyEntry) {
                    $transaction = $buyEntry['transaction'];
                    $remainingQuantity = $buyEntry['remaining'];
                    // Calculate proportional investment for remaining shares
                    return ($transaction->net_amount / $transaction->quantity) * $remainingQuantity;
                });

                // Calculate average price from current holdings
                $avgPrice = $currentHoldingsInvestment > 0 && $currentHoldingsQuantity > 0
                    ? $currentHoldingsInvestment / $currentHoldingsQuantity
                    : ($totalInvestment > 0 ? $totalInvestment / $currentHoldingsQuantity : 0);

                // If current price is available and > 0, use it. Otherwise, use avg price
                $currentPrice = ($representativeStock->current_price && floatval($representativeStock->current_price) > 0)
                    ? floatval($representativeStock->current_price)
                    : $avgPrice;
                $currentValue = $currentHoldingsQuantity * $currentPrice;

                // Calculate unrealized P&L based on current holdings investment
                $unrealizedGainLoss = $currentValue - $currentHoldingsInvestment;
                $unrealizedGainLossPercent = $currentHoldingsInvestment > 0 ? ($unrealizedGainLoss / $currentHoldingsInvestment) * 100 : 0;

                // Collect all exchanges for this symbol
                $exchanges = $stockTransactions->pluck('stock.exchange')->unique()->implode('/');

                // Get dividend information for this stock
                $dividendEligibility = $this->dividendService->calculateDividendEligibility($userId, $representativeStock->id);
                $roiWithDividends = $this->dividendService->getROIWithDividends($userId, $representativeStock->id);

                // Enhanced transactions - only show transactions that contribute to current holdings
                $enhancedTransactions = $currentHoldingTransactions
                    ->sortByDesc(function ($buyEntry) {
                        return $buyEntry['transaction']->transaction_date;
                    })
                    ->map(function ($buyEntry) use ($currentPrice) {
                        $transaction = $buyEntry['transaction'];
                        $remainingQuantity = $buyEntry['remaining'];

                        $daysHeld = $transaction->transaction_date->diffInDays(now());

                        // Calculate proportional investment for remaining shares
                        $proportionalInvestment = ($transaction->net_amount / $transaction->quantity) * $remainingQuantity;

                        // For buy/bonus transactions, show unrealized P&L based on remaining quantity
                        $transactionCurrentValue = $remainingQuantity * $currentPrice;
                        $transactionPL = $transactionCurrentValue - $proportionalInvestment;

                        return array_merge($transaction->toArray(), [
                            'quantity' => $remainingQuantity, // Override with remaining quantity
                            'net_amount' => round($proportionalInvestment, 2), // Override with proportional investment
                            'days_held' => $daysHeld,
                            'current_value' => round($transactionCurrentValue, 0),
                            'pl_amount' => round($transactionPL, 0),
                            'pl_percent' => $proportionalInvestment > 0 ? round(($transactionPL / $proportionalInvestment) * 100, 2) : 0,
                            'is_bonus' => $transaction->price_per_stock == 0,
                            'is_sell' => false, // Only showing buy/bonus transactions
                        ]);
                    });

                $tempHoldings[] = [
                    'stock_id' => $representativeStock->id,
                    'symbol' => $symbol,
                    'name' => $representativeStock->name,
                    'exchange' => $exchanges,
                    'sector' => $representativeStock->sector,
                    'quantity' => $currentHoldingsQuantity,
                    'avg_price' => round($avgPrice, 2),
                    'current_price' => $currentPrice,
                    'total_investment' => round($currentHoldingsInvestment, 2), // Show only current holdings investment
                    'current_value' => round($currentValue, 0),
                    'unrealized_gain_loss' => round($unrealizedGainLoss, 0),
                    'unrealized_gain_loss_percent' => round($unrealizedGainLossPercent, 2),
                    'realized_gain_loss' => round($realizedPL, 0),
                    'total_sell_proceeds' => round($totalSellProceeds, 2),
                    'historical_investment' => round($totalInvestment, 2), // Keep historical for reporting
                    'day_change' => $representativeStock->day_change,
                    'day_change_percent' => $representativeStock->day_change_percent,
                    'week_52_high' => $representativeStock->week_52_high,
                    'week_52_low' => $representativeStock->week_52_low,
                    'transaction_count' => $currentHoldingTransactions->count(),
                    'buy_transaction_count' => $currentHoldingTransactions->filter(function ($buyEntry) {
                        return $buyEntry['transaction']->transaction_type === 'buy';
                    })->count(),
                    'sell_transaction_count' => 0, // No sell transactions shown for current holdings
                    'transactions' => $enhancedTransactions->values(),
                    // Dividend information
                    'dividend_data' => [
                        'total_dividends_received' => round($roiWithDividends['total_dividends_received'], 2),
                        'pending_dividends' => round($roiWithDividends['pending_dividends'], 2),
                        'dividend_yield' => round($roiWithDividends['dividend_yield'], 2),
                        'dividend_adjusted_roi' => round($roiWithDividends['dividend_adjusted_roi'], 2),
                        'recent_dividends' => collect($dividendEligibility)->take(3)->map(function ($dividend) {
                            return [
                                'ex_dividend_date' => $dividend['dividend_payment']->ex_dividend_date,
                                'dividend_date' => $dividend['dividend_payment']->dividend_date,
                                'amount_per_share' => $dividend['dividend_payment']->dividend_amount,
                                'total_amount' => $dividend['total_amount'],
                                'status' => $dividend['user_record']->status,
                            ];
                        })->toArray(),
                        'has_upcoming_dividend' => collect($dividendEligibility)->filter(function ($dividend) {
                            return $dividend['dividend_payment']->dividend_date > now();
                        })->isNotEmpty(),
                    ],
                ];

                $totalPortfolioValue += $currentValue;
            }
        }

        // Calculate weight percentages
        $holdings = [];
        foreach ($tempHoldings as $holding) {
            $holding['weight_percent'] = $totalPortfolioValue > 0 ?
                round(($holding['current_value'] / $totalPortfolioValue) * 100, 2) : 0;
            $holdings[] = $holding;
        }

        return collect($holdings)->sortBy('symbol')->values();
    }

    private function calculatePortfolioMetrics($holdings)
    {
        if (empty($holdings)) {
            return [
                'todaysChange' => 0,
                'todaysChangePercent' => 0,
                'bestPerformers' => [],
                'worstPerformers' => [],
                'portfolioDiversity' => [
                    'totalHoldings' => 0,
                    'maxConcentration' => 0,
                    'sectorBreakdown' => []
                ]
            ];
        }

        $totalValue = collect($holdings)->sum('current_value');
        $totalInvestment = collect($holdings)->sum('total_investment');

        // Calculate today's change (sum of all day changes weighted by holdings)
        $todaysChange = 0;
        foreach ($holdings as $holding) {
            if ($holding['day_change'] && $holding['quantity']) {
                $todaysChange += $holding['day_change'] * $holding['quantity'];
            }
        }
        $todaysChangePercent = $totalValue > 0 ? ($todaysChange / $totalValue) * 100 : 0;

        // Sort holdings by P&L percentage for best/worst performers
        $sortedByPL = collect($holdings)->sortByDesc('unrealized_gain_loss_percent');

        $bestPerformers = $sortedByPL->take(3)->map(function ($holding) {
            return [
                'symbol' => $holding['symbol'],
                'plPercent' => $holding['unrealized_gain_loss_percent']
            ];
        })->values();

        $worstPerformers = $sortedByPL->reverse()->take(3)->map(function ($holding) {
            return [
                'symbol' => $holding['symbol'],
                'plPercent' => $holding['unrealized_gain_loss_percent']
            ];
        })->values();

        // Calculate portfolio diversity
        $maxConcentration = $totalValue > 0 ? collect($holdings)->max('weight_percent') : 0;

        // Sector breakdown
        $sectorBreakdown = [];
        foreach ($holdings as $holding) {
            $sector = $holding['sector'] ?? 'Unknown';
            if (!isset($sectorBreakdown[$sector])) {
                $sectorBreakdown[$sector] = 0;
            }
            $sectorBreakdown[$sector] += $holding['weight_percent'];
        }

        return [
            'todaysChange' => round($todaysChange, 0),
            'todaysChangePercent' => round($todaysChangePercent, 2),
            'bestPerformers' => $bestPerformers,
            'worstPerformers' => $worstPerformers,
            'portfolioDiversity' => [
                'totalHoldings' => count($holdings),
                'maxConcentration' => round($maxConcentration, 1),
                'sectorBreakdown' => $sectorBreakdown
            ]
        ];
    }

    /**
     * Update dividend data for stocks
     */
    public function updateDividendData(Request $request)
    {
        try {
            $stockIds = $request->get('stock_ids', []);
            $user = Auth::user();

            if (empty($stockIds)) {
                // Update all user's stocks
                $stocks = Stock::whereHas('transactions', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->get();
            } else {
                $stocks = Stock::whereIn('id', $stockIds)->get();
            }

            $updated = 0;
            $eligibilityRecalculated = 0;
            
            foreach ($stocks as $stock) {
                if ($this->dividendService->updateDividendData($stock)) {
                    $updated++;
                }
                
                // Recalculate dividend eligibility for the current user
                // This ensures that changes in transaction dates are reflected in dividend calculations
                try {
                    $this->dividendService->calculateDividendEligibility($user->id, $stock->id);
                    $eligibilityRecalculated++;
                } catch (\Exception $e) {
                    // Log the error but don't fail the entire operation
                    \Illuminate\Support\Facades\Log::warning("Failed to recalculate dividend eligibility for stock {$stock->symbol}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Updated dividend data for {$updated} stocks and recalculated eligibility for {$eligibilityRecalculated} stocks",
                'updated_count' => $updated,
                'eligibility_recalculated' => $eligibilityRecalculated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update dividend data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed dividend information for a specific stock
     */
    public function getDividendDetails($stockId)
    {
        try {
            $user = Auth::user();
            $eligibility = $this->dividendService->calculateDividendEligibility($user->id, $stockId);
            $roiData = $this->dividendService->getROIWithDividends($user->id, $stockId);

            return response()->json([
                'success' => true,
                'eligibility' => $eligibility,
                'roi_data' => $roiData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dividend details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark dividend as received
     */
    public function markDividendReceived(Request $request)
    {
        $validated = $request->validate([
            'user_dividend_record_id' => 'required|integer|exists:user_dividend_records,id',
            'received_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $user = Auth::user();
            $record = \App\Models\UserDividendRecord::where('id', $validated['user_dividend_record_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            $record->update([
                'status' => 'received',
                'received_date' => $validated['received_date'],
                'notes' => $validated['notes'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dividend marked as received',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark dividend as received: ' . $e->getMessage(),
            ], 500);
        }
    }
}
