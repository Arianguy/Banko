<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class EquityHoldingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Get all stock holdings for the user
        $holdings = $this->calculateHoldings($user->id);

        // Calculate additional metrics
        $portfolioMetrics = $this->calculatePortfolioMetrics($holdings);

        return Inertia::render('EquityHolding/Index', [
            'holdings' => $holdings,
            'portfolioMetrics' => $portfolioMetrics,
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

    public function syncPrices()
    {
        try {
            // Run the stock prices update command
            Artisan::call('stock-prices:update');
            $output = Artisan::output();

            // Parse the output to get success/failure counts
            preg_match('/Successfully updated: (\d+) stocks/', $output, $successMatches);
            preg_match('/Failed to update: (\d+) stocks/', $output, $failMatches);

            $successCount = $successMatches[1] ?? 0;
            $failCount = $failMatches[1] ?? 0;

            if ($successCount > 0) {
                $message = "✅ Successfully updated {$successCount} stock prices using multi-API system";
                if ($failCount > 0) {
                    $message .= " (❌ {$failCount} failed)";
                }
                return redirect()->route('equity-holding.index')->with('success', $message);
            } else {
                return redirect()->route('equity-holding.index')->with('error', "❌ Failed to update any stock prices. All APIs may be unavailable.");
            }
        } catch (\Exception $e) {
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
            ->get();

        $totalQuantity = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                $totalQuantity += $transaction->quantity;
            } elseif ($transaction->transaction_type === 'sell') {
                $totalQuantity -= $transaction->quantity;
            }
        }

        return $totalQuantity;
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

            // Calculate net quantity and investment
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

                // Calculate realized P&L (simplified FIFO method)
                $avgCostBasis = $totalInvestment > 0 ? $totalInvestment / ($totalQuantity + $transaction->quantity) : 0;
                $costOfSoldShares = $avgCostBasis * $transaction->quantity;
                $realizedPL += $transaction->net_amount - $costOfSoldShares;
            }

            if ($totalQuantity > 0) {
                $avgPrice = $totalInvestment > 0 ? $totalInvestment / $totalQuantity : 0;

                // If current price is available and > 0, use it. Otherwise, use avg price
                $currentPrice = ($representativeStock->current_price && floatval($representativeStock->current_price) > 0)
                    ? floatval($representativeStock->current_price)
                    : $avgPrice;
                $currentValue = $totalQuantity * $currentPrice;
                $unrealizedGainLoss = $currentValue - $totalInvestment;
                $unrealizedGainLossPercent = $totalInvestment > 0 ? ($unrealizedGainLoss / $totalInvestment) * 100 : 0;

                // Collect all exchanges for this symbol
                $exchanges = $stockTransactions->pluck('stock.exchange')->unique()->implode('/');

                // Enhanced transactions with additional metrics (include all transaction types)
                $allTransactions = $stockTransactions->sortByDesc('transaction_date');
                $enhancedTransactions = $allTransactions->map(function ($transaction) use ($currentPrice) {
                    $daysHeld = now()->diffInDays($transaction->transaction_date);

                    if ($transaction->transaction_type === 'sell') {
                        // For sell transactions, show realized P&L
                        $transactionPL = $transaction->net_amount - ($transaction->quantity * $transaction->price_per_stock);
                        $transactionCurrentValue = 0; // Already sold
                    } else {
                        // For buy/bonus transactions, show unrealized P&L
                        $transactionCurrentValue = $transaction->quantity * $currentPrice;
                        $transactionPL = $transactionCurrentValue - $transaction->net_amount;
                    }

                    return array_merge($transaction->toArray(), [
                        'days_held' => $daysHeld,
                        'current_value' => round($transactionCurrentValue, 0),
                        'pl_amount' => round($transactionPL, 0),
                        'pl_percent' => $transaction->net_amount > 0 ? round(($transactionPL / $transaction->net_amount) * 100, 2) : 0,
                        'is_bonus' => $transaction->price_per_stock == 0,
                        'is_sell' => $transaction->transaction_type === 'sell',
                    ]);
                });

                $tempHoldings[] = [
                    'stock_id' => $representativeStock->id,
                    'symbol' => $symbol,
                    'name' => $representativeStock->name,
                    'exchange' => $exchanges,
                    'sector' => $representativeStock->sector,
                    'quantity' => $totalQuantity,
                    'avg_price' => round($avgPrice, 2),
                    'current_price' => $currentPrice,
                    'total_investment' => round($totalInvestment, 2),
                    'current_value' => round($currentValue, 0),
                    'unrealized_gain_loss' => round($unrealizedGainLoss, 0),
                    'unrealized_gain_loss_percent' => round($unrealizedGainLossPercent, 2),
                    'realized_gain_loss' => round($realizedPL, 0),
                    'total_sell_proceeds' => round($totalSellProceeds, 2),
                    'day_change' => $representativeStock->day_change,
                    'day_change_percent' => $representativeStock->day_change_percent,
                    'week_52_high' => $representativeStock->week_52_high,
                    'week_52_low' => $representativeStock->week_52_low,
                    'transaction_count' => $stockTransactions->count(),
                    'buy_transaction_count' => $buyTransactions->count(),
                    'sell_transaction_count' => $sellTransactions->count(),
                    'transactions' => $enhancedTransactions->values(),
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
}
