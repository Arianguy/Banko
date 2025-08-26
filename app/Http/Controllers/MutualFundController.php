<?php

namespace App\Http\Controllers;

use App\Models\MutualFund;
use App\Models\MutualFundTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            
            $totalUnits = 0;
            $totalInvestment = 0;
            $enhancedTransactions = [];

            foreach ($fundTransactions as $transaction) {
                if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'sip') {
                    $totalUnits += $transaction->units;
                    $totalInvestment += $transaction->net_amount;
                } elseif ($transaction->transaction_type === 'sell') {
                    $totalUnits -= $transaction->units;
                    $totalInvestment -= ($transaction->net_amount / $transaction->units) * $transaction->units;
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

        $validated['user_id'] = Auth::id();

        MutualFundTransaction::create($validated);

        return redirect()->route('mutual-funds.index')
            ->with('success', 'Mutual fund transaction added successfully!');
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
}
