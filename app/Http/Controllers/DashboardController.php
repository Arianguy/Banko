<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockTransaction;
use App\Models\FixedDeposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = $request->get('year', $this->getCurrentFinancialYear());
        $equityData = $this->getEquityData($selectedYear);
        $fixedDepositData = $this->getFixedDepositData($selectedYear);
        
        return Inertia::render('dashboard', [
            'equity_data' => $equityData,
            'fixed_deposit_data' => $fixedDepositData,
            'current_year' => $selectedYear,
            'available_years' => $this->getAvailableYears()
        ]);
    }

    private function getCurrentFinancialYear()
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        
        // If current month is before April, financial year is previous year
        if ($now->month < 4) {
            return ($currentYear - 1) . '-' . $currentYear;
        }
        
        return $currentYear . '-' . ($currentYear + 1);
    }

    private function getAvailableYears()
    {
        $transactions = StockTransaction::where('user_id', Auth::id())
            ->selectRaw('YEAR(transaction_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        $years = [];
        foreach ($transactions as $year) {
            // Create financial year format
            $years[] = $year . '-' . ($year + 1);
            if ($year > 2020) { // Add previous year as well
                $years[] = ($year - 1) . '-' . $year;
            }
        }

        // Remove duplicates and sort in descending order
        $uniqueYears = array_unique($years);
        rsort($uniqueYears);
        
        return $uniqueYears;
    }

    private function getEquityData($financialYear)
    {
        [$startYear, $endYear] = explode('-', $financialYear);
        
        $startDate = Carbon::createFromDate($startYear, 4, 1)->startOfDay();
        $endDate = Carbon::createFromDate($endYear, 3, 31)->endOfDay();

        // Get all transactions for the selected financial year
        $transactions = StockTransaction::with('stock')
            ->where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        $totalInvested = 0;
        $realizedPL = 0;
        $unrealizedPL = 0;
        $currentValue = 0;
        $totalDividends = 0;

        // Calculate total invested (buy transactions)
        $buyTransactions = $transactions->where('transaction_type', 'buy');
        $totalInvested = $buyTransactions->sum('net_amount');

        // Calculate realized P&L (sell transactions)
        $sellTransactions = $transactions->where('transaction_type', 'sell');
        foreach ($sellTransactions as $sellTransaction) {
            $realizedPL += $this->calculateRealizedGainLoss(Auth::id(), $sellTransaction);
        }

        // Calculate current holdings and unrealized P&L
        $currentHoldings = $this->calculateCurrentHoldings(Auth::id(), $endDate);
        
        foreach ($currentHoldings as $holding) {
            $currentValue += $holding['current_value'];
            $unrealizedPL += $holding['unrealized_gain_loss'];
        }

        // Calculate dividends received in the financial year
        $dividendRecords = \App\Models\UserDividendRecord::with('dividendPayment')
            ->where('user_id', Auth::id())
            ->where('status', 'received')
            ->whereHas('dividendPayment', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('dividend_date', [$startDate, $endDate]);
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

    private function getFixedDepositData($financialYear)
    {
        [$startYear, $endYear] = explode('-', $financialYear);
        
        $startDate = Carbon::createFromDate($startYear, 4, 1)->startOfDay();
        $endDate = Carbon::createFromDate($endYear, 3, 31)->endOfDay();

        // Get all fixed deposits that were active during the selected financial year
        $fixedDeposits = FixedDeposit::where('user_id', Auth::id())
            ->where(function ($query) use ($startDate, $endDate) {
                // FD started before or during the financial year AND
                // FD matures after or during the financial year
                $query->where('start_date', '<=', $endDate)
                      ->where('maturity_date', '>=', $startDate);
            })
            ->where('closed', false) // Only active FDs
            ->get();

        $totalPrincipal = $fixedDeposits->sum('principal_amt');
        
        // Calculate unrealized interest (interest that would be earned if held to maturity)
        $totalUnrealizedInterest = 0;
        $bankWiseData = [];

        foreach ($fixedDeposits as $fd) {
            // Calculate unrealized interest based on current date vs maturity date
            $currentDate = Carbon::now();
            $startDate = Carbon::parse($fd->start_date);
            $maturityDate = Carbon::parse($fd->maturity_date);
            
            if ($currentDate->gte($maturityDate)) {
                // FD has matured, use full interest
                $unrealizedInterest = $fd->Int_amt;
            } else {
                // FD is still active, calculate pro-rated interest
                $totalDays = $startDate->diffInDays($maturityDate);
                $elapsedDays = $startDate->diffInDays($currentDate);
                $unrealizedInterest = ($fd->Int_amt * $elapsedDays) / $totalDays;
            }
            
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

    private function calculateCurrentHoldings($userId, $endDate)
    {
        $transactions = StockTransaction::with('stock')
            ->where('user_id', $userId)
            ->where('transaction_date', '<=', $endDate)
            ->get()
            ->groupBy('stock.symbol');

        $holdings = [];

        foreach ($transactions as $symbol => $stockTransactions) {
            $totalQuantity = 0;
            $totalInvestment = 0;

            $buyTransactions = $stockTransactions->where('transaction_type', 'buy');
            $sellTransactions = $stockTransactions->where('transaction_type', 'sell');
            $bonusTransactions = $stockTransactions->where('transaction_type', 'bonus');

            // Calculate net quantity
            foreach ($buyTransactions as $transaction) {
                $totalQuantity += $transaction->quantity;
                $totalInvestment += $transaction->net_amount;
            }

            foreach ($bonusTransactions as $transaction) {
                $totalQuantity += $transaction->quantity;
            }

            foreach ($sellTransactions as $transaction) {
                $totalQuantity -= $transaction->quantity;
            }

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
}