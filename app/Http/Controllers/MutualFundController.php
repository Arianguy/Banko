<?php

namespace App\Http\Controllers;

use App\Models\MutualFund;
use App\Models\MutualFundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class MutualFundController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Get all mutual fund holdings for the user
        $holdings = $this->calculateHoldings($user->id);

        // Calculate additional metrics
        $portfolioMetrics = $this->calculatePortfolioMetrics($holdings);

        return Inertia::render('MutualFunds/Index', [
            'holdings' => $holdings,
            'portfolioMetrics' => $portfolioMetrics,
        ]);
    }

    private function calculateHoldings($userId)
    {
        $transactions = MutualFundTransaction::with('mutualFund')
            ->where('user_id', $userId)
            ->orderBy('transaction_date', 'desc')
            ->get();

        $holdings = [];
        $groupedTransactions = $transactions->groupBy('mutual_fund_id');

        foreach ($groupedTransactions as $mutualFundId => $fundTransactions) {
            $mutualFund = $fundTransactions->first()->mutualFund;
            
            // Sort transactions chronologically for FIFO calculation
            $sortedTransactions = $fundTransactions->sortBy(['transaction_date', 'id']);
            
            $buyQueue = collect();
            $totalUnits = 0;
            $totalInvestment = 0;
            $enhancedTransactions = [];

            // Process transactions using FIFO logic
            foreach ($sortedTransactions as $transaction) {
                if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'sip') {
                    $totalUnits += $transaction->units;
                    $totalInvestment += $transaction->net_amount;
                    
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

                    $totalInvestment -= $investmentToReduce;
                }

                $daysHeld = $transaction->transaction_date->diffInDays(now());
                
                $enhancedTransactions[] = [
                    'id' => $transaction->id,
                    'transaction_type' => $transaction->transaction_type,
                    'units' => $transaction->units,
                    'nav' => $transaction->nav,
                    'amount' => $transaction->amount,
                    'net_amount' => $transaction->net_amount,
                    'transaction_date' => $transaction->transaction_date,
                    'folio_number' => $transaction->folio_number,
                    'days_held' => $daysHeld,
                    'notes' => $transaction->notes,
                ];
            }

            if ($totalUnits > 0) {
                $currentValue = $totalUnits * ($mutualFund->current_nav ?? 0);
                $totalPL = $currentValue - $totalInvestment;
                $totalPLPercent = $totalInvestment > 0 ? ($totalPL / $totalInvestment) * 100 : 0;
                $avgNav = $totalInvestment > 0 ? $totalInvestment / $totalUnits : 0;

                $holdings[] = [
                    'mutual_fund_id' => $mutualFund->id,
                    'scheme_code' => $mutualFund->scheme_code,
                    'scheme_name' => $mutualFund->scheme_name,
                    'fund_house' => $mutualFund->fund_house,
                    'category' => $mutualFund->category,
                    'current_nav' => $mutualFund->current_nav,
                    'nav_date' => $mutualFund->nav_date,
                    'total_units' => $totalUnits,
                    'avg_nav' => $avgNav,
                    'total_investment' => $totalInvestment,
                    'current_value' => $currentValue,
                    'total_pl' => $totalPL,
                    'total_pl_percent' => $totalPLPercent,
                    'transactions' => $enhancedTransactions,
                ];
            }
        }

        return collect($holdings)->sortByDesc('current_value')->values()->all();
    }

    private function calculatePortfolioMetrics($holdings)
    {
        $totalInvestment = collect($holdings)->sum('total_investment');
        $totalCurrentValue = collect($holdings)->sum('current_value');
        $totalPL = $totalCurrentValue - $totalInvestment;
        $totalPLPercent = $totalInvestment > 0 ? ($totalPL / $totalInvestment) * 100 : 0;

        $bestPerformer = collect($holdings)->sortByDesc('total_pl_percent')->first();
        $worstPerformer = collect($holdings)->sortBy('total_pl_percent')->first();

        $fundHouseDistribution = collect($holdings)
            ->groupBy('fund_house')
            ->map(function ($group) {
                return $group->sum('current_value');
            })
            ->sortDesc();

        $categoryDistribution = collect($holdings)
            ->groupBy('category')
            ->map(function ($group) {
                return $group->sum('current_value');
            })
            ->sortDesc();

        return [
            'total_investment' => $totalInvestment,
            'total_current_value' => $totalCurrentValue,
            'total_pl' => $totalPL,
            'total_pl_percent' => $totalPLPercent,
            'best_performer' => $bestPerformer,
            'worst_performer' => $worstPerformer,
            'fund_house_distribution' => $fundHouseDistribution,
            'category_distribution' => $categoryDistribution,
            'total_schemes' => count($holdings),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mutual_fund_id' => 'required|exists:mutual_funds,id',
            'transaction_type' => 'required|in:buy,sell,sip,dividend_reinvestment,redemption',
            'units' => 'required|numeric|min:0.000001',
            'nav' => 'required|numeric|min:0.01',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'folio_number' => 'nullable|string|max:255',
            'stamp_duty' => 'nullable|numeric|min:0',
            'transaction_charges' => 'nullable|numeric|min:0',
            'gst' => 'nullable|numeric|min:0',
            'net_amount' => 'required|numeric|min:0.01',
            'order_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();

        $transaction = MutualFundTransaction::create($validated);

        // Check if this is an AJAX request
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Mutual fund transaction added successfully!',
                'transaction' => $transaction
            ]);
        }

        return redirect()->route('mutual-funds.index')
            ->with('success', 'Mutual fund transaction added successfully!');
    }

    public function update(Request $request, $id)
    {
        $transaction = MutualFundTransaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'transaction_type' => 'required|in:buy,sell,sip,dividend_reinvestment',
            'units' => 'required|numeric|min:0.000001',
            'nav' => 'required|numeric|min:0.01',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'folio_number' => 'nullable|string|max:255',
            'stamp_duty' => 'nullable|numeric|min:0',
            'transaction_charges' => 'nullable|numeric|min:0',
            'gst' => 'nullable|numeric|min:0',
            'net_amount' => 'required|numeric|min:0.01',
            'order_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $transaction->update($validated);

        return redirect()->route('mutual-funds.index')
            ->with('success', 'Mutual fund transaction updated successfully!');
    }

    public function destroy($id)
    {
        $transaction = MutualFundTransaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $transaction->delete();

        return redirect()->route('mutual-funds.index')
            ->with('success', 'Mutual fund transaction deleted successfully!');
    }

    public function searchFunds(Request $request)
    {
        $query = $request->get('query', '');
        
        $funds = MutualFund::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('scheme_name', 'LIKE', "%{$query}%")
                  ->orWhere('scheme_code', 'LIKE', "%{$query}%")
                  ->orWhere('fund_house', 'LIKE', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'scheme_code', 'scheme_name', 'fund_house', 'current_nav']);

        return response()->json($funds);
    }

    public function syncNavs(Request $request)
    {
        try {
            $fundIds = $request->input('fund_ids');

            if ($fundIds) {
                // Update NAVs for specific funds only
                $command = 'mutual-fund-navs:update';
                foreach ($fundIds as $fundId) {
                    Artisan::call($command, ['--fund' => $fundId]);
                }
                $output = "Successfully updated NAVs for user's portfolio funds";
            } else {
                // Run the mutual fund NAV update command for all funds
                Artisan::call('mutual-fund-navs:update');
                $output = Artisan::output();
            }

            // Parse the output to get success/failure counts (for all funds sync)
            if (!$fundIds) {
                preg_match('/Successfully updated: (\d+) funds/', $output, $successMatches);
                preg_match('/Failed to update: (\d+) funds/', $output, $failMatches);

                $successCount = $successMatches[1] ?? 0;
                $failCount = $failMatches[1] ?? 0;

                if ($successCount > 0) {
                    $message = "✅ Successfully updated {$successCount} mutual fund NAVs from AMFI";
                    if ($failCount > 0) {
                        $message .= " (❌ {$failCount} failed)";
                    }
                } else {
                    $message = "❌ Failed to update mutual fund NAVs. Please try again later.";
                }
            } else {
                $message = "✅ Successfully updated NAVs for selected mutual funds";
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating NAVs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSoldHistory()
    {
        $user = Auth::user();

        // Get all sell/redemption transactions
        $sellTransactions = MutualFundTransaction::with('mutualFund')
            ->where('user_id', $user->id)
            ->whereIn('transaction_type', ['sell', 'redemption'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        $soldHistory = [];

        foreach ($sellTransactions as $sellTransaction) {
            $mutualFund = $sellTransaction->mutualFund;
            
            // Get all transactions for this fund up to the sell date
            $allTransactions = MutualFundTransaction::where('user_id', $user->id)
                ->where('mutual_fund_id', $sellTransaction->mutual_fund_id)
                ->where('transaction_date', '<=', $sellTransaction->transaction_date)
                ->orderBy('transaction_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($allTransactions->isNotEmpty()) {
                // Use FIFO logic to calculate the actual cost of sold units
                $buyQueue = collect();
                $soldUnitsInvestment = 0;
                $soldUnitsCount = 0;
                
                // Process all transactions chronologically to build the buy queue
                foreach ($allTransactions as $transaction) {
                    if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'sip') {
                        // Add to buy queue
                        $buyQueue->push([
                            'units' => $transaction->units,
                            'remaining' => $transaction->units,
                            'nav' => $transaction->nav,
                            'net_amount' => $transaction->net_amount,
                            'avg_cost_per_unit' => $transaction->net_amount / $transaction->units
                        ]);
                    } elseif ($transaction->id === $sellTransaction->id) {
                        // This is our target sell transaction - calculate FIFO cost
                        $remainingToSell = $transaction->units;
                        
                        while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                            $buyEntry = $buyQueue->first();

                            if ($buyEntry['remaining'] <= $remainingToSell) {
                                // Consume entire buy entry
                                $soldUnitsInvestment += $buyEntry['remaining'] * $buyEntry['avg_cost_per_unit'];
                                $soldUnitsCount += $buyEntry['remaining'];
                                $remainingToSell -= $buyEntry['remaining'];
                                $buyQueue->shift();
                            } else {
                                // Partially consume buy entry
                                $soldUnitsInvestment += $remainingToSell * $buyEntry['avg_cost_per_unit'];
                                $soldUnitsCount += $remainingToSell;
                                $buyEntry['remaining'] -= $remainingToSell;
                                $buyQueue[0] = $buyEntry;
                                $remainingToSell = 0;
                            }
                        }
                        break; // We found our target transaction
                    } elseif ($transaction->transaction_type === 'sell' || $transaction->transaction_type === 'redemption') {
                        // Process other sell transactions that happened before our target
                        $remainingToSell = $transaction->units;
                        
                        while ($remainingToSell > 0 && $buyQueue->isNotEmpty()) {
                            $buyEntry = $buyQueue->first();

                            if ($buyEntry['remaining'] <= $remainingToSell) {
                                // Consume entire buy entry
                                $remainingToSell -= $buyQueue->shift()['remaining'];
                            } else {
                                // Partially consume buy entry
                                $buyEntry['remaining'] -= $remainingToSell;
                                $buyQueue[0] = $buyEntry;
                                $remainingToSell = 0;
                            }
                        }
                    }
                }

                // Only include this sell transaction if we actually sold some units (i.e., there were buy transactions)
                if ($soldUnitsCount > 0) {
                    // Calculate average buy NAV for the sold units using FIFO
                    $avgBuyNav = $soldUnitsInvestment / $soldUnitsCount;
                    
                    // Calculate realized P&L
                    $realizedPL = $sellTransaction->net_amount - $soldUnitsInvestment;

                    $soldHistory[] = [
                        'scheme_name' => $mutualFund->scheme_name,
                        'fund_house' => $mutualFund->fund_house,
                        'category' => $mutualFund->category,
                        'units_sold' => round($sellTransaction->units, 3),
                        'avg_buy_nav' => round($avgBuyNav, 4),
                        'sell_nav' => $sellTransaction->nav,
                        'total_investment' => round($soldUnitsInvestment, 2),
                        'total_proceeds' => $sellTransaction->net_amount,
                        'realized_pl' => round($realizedPL, 2),
                        'sell_date' => $sellTransaction->transaction_date->format('Y-m-d'),
                        'transaction_type' => $sellTransaction->transaction_type,
                        'folio_number' => $sellTransaction->folio_number,
                    ];
                }
            }
        }

        return response()->json($soldHistory);
    }

    public function addFund(Request $request)
    {
        $validated = $request->validate([
            'scheme_name' => 'required|string|max:255',
            'scheme_code' => 'required|string|max:50|unique:mutual_funds,scheme_code',
            'fund_house' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'sub_category' => 'required|string|max:100',
            'current_nav' => 'nullable|numeric|min:0.01',
        ]);

        try {
            $mutualFund = MutualFund::create([
                'scheme_name' => $validated['scheme_name'],
                'scheme_code' => $validated['scheme_code'],
                'fund_house' => $validated['fund_house'],
                'category' => $validated['category'],
                'sub_category' => $validated['sub_category'],
                'current_nav' => $validated['current_nav'] ?? null,
                'nav_date' => $validated['current_nav'] ? now() : null,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mutual fund added successfully!',
                'fund' => $mutualFund
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding mutual fund: ' . $e->getMessage()
            ], 500);
        }
    }
}
