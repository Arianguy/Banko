<?php

namespace App\Http\Controllers;

use App\Models\FixedDeposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PortfolioController extends Controller
{
    public function getSummaryMetrics()
    {
        $userFds = Auth::user()->fixedDeposits()->get();
        // Alternative: $userFds = FixedDeposit::where('user_id', Auth::id())->get();

        $total_principal_invested = $userFds->sum('principal_amt');
        $total_maturity_value = $userFds->sum('maturity_amt');
        $total_interest_earned = $userFds->sum(function ($fd) {
            return $fd->maturity_amt - $fd->principal_amt;
        });

        $sum_principal_times_rate = $userFds->sum(function ($fd) {
            return $fd->principal_amt * $fd->int_rate;
        });

        $weighted_average_interest_rate = 0;
        if ($total_principal_invested > 0) {
            $weighted_average_interest_rate = ($sum_principal_times_rate / $total_principal_invested);
        }

        return response()->json([
            'total_principal_invested' => $total_principal_invested,
            'total_maturity_value' => $total_maturity_value,
            'total_interest_earned' => $total_interest_earned,
            'weighted_average_interest_rate' => $weighted_average_interest_rate,
        ]);
    }

    public function getBankDistribution()
    {
        $userFds = Auth::user()->fixedDeposits()->get();

        $distribution = $userFds->groupBy('bank')
            ->map(function ($group) {
                return $group->sum('principal_amt');
            })
            ->map(function($total, $bankName) { // Prepare for chart
                return [$bankName, $total];
            })->values(); // Get as a simple array of arrays

        // Add header for chart
        array_unshift($distribution, ['Bank Name', 'Total Principal']);

        return response()->json($distribution);
    }

    public function getMaturityYearBreakdown()
    {
        $userFds = Auth::user()->fixedDeposits()->get();

        $breakdown = $userFds->groupBy(function($fd) {
                return Carbon::parse($fd->maturity_date)->year;
            })
            ->map(function ($group) {
                return $group->sum('principal_amt');
            })
            ->sortKeys() // Sort by year
            ->map(function($total, $year) { // Prepare for chart
                return [(string)$year, $total];
            })->values(); // Get as a simple array of arrays

        // Add header for chart
        array_unshift($breakdown, ['Year', 'Total Principal']);
        
        return response()->json($breakdown);
    }
}
