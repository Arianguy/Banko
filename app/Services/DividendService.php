<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\DividendPayment;
use App\Models\UserDividendRecord;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DividendService
{
    /**
     * Fetch dividend data from Yahoo Finance API for a stock
     */
    public function fetchDividendDataFromYahoo(Stock $stock): ?array
    {
        $symbols = [$stock->symbol . '.NS', $stock->symbol . '.BO'];

        foreach ($symbols as $symbol) {
            try {
                // Try quoteSummary first for detailed dividend information
                $response = Http::timeout(10)->get("https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$symbol}", [
                    'modules' => 'calendarEvents,dividendsHistory,summaryDetail'
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['quoteSummary']['result'][0])) {
                        $result = $data['quoteSummary']['result'][0];
                        $dividendData = [];

                        // Extract calendar events for upcoming dividends
                        if (isset($result['calendarEvents'])) {
                            $calendarEvents = $result['calendarEvents'];

                            if (isset($calendarEvents['exDividendDate']) || isset($calendarEvents['dividendDate'])) {
                                $dividendData['upcoming'] = [
                                    'ex_dividend_date' => isset($calendarEvents['exDividendDate'])
                                        ? Carbon::createFromTimestamp($calendarEvents['exDividendDate']['raw'])->format('Y-m-d')
                                        : null,
                                    'dividend_date' => isset($calendarEvents['dividendDate'])
                                        ? Carbon::createFromTimestamp($calendarEvents['dividendDate']['raw'])->format('Y-m-d')
                                        : null,
                                ];
                            }
                        }

                        // Extract summary detail for dividend rate and yield
                        if (isset($result['summaryDetail'])) {
                            $summaryDetail = $result['summaryDetail'];

                            $dividendData['current'] = [
                                'dividend_rate' => $summaryDetail['dividendRate']['raw'] ?? 0,
                                'dividend_yield' => $summaryDetail['dividendYield']['raw'] ?? 0,
                                'trailing_annual_dividend_rate' => $summaryDetail['trailingAnnualDividendRate']['raw'] ?? 0,
                                'trailing_annual_dividend_yield' => $summaryDetail['trailingAnnualDividendYield']['raw'] ?? 0,
                            ];
                        }

                        return $dividendData;
                    }
                }

                // Fallback: Try chart API for dividend events
                $chartResponse = Http::timeout(10)->get("https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}", [
                    'period1' => Carbon::now()->subYear()->timestamp,
                    'period2' => Carbon::now()->addMonths(6)->timestamp,
                    'interval' => '1d',
                    'events' => 'div'
                ]);

                if ($chartResponse->successful()) {
                    $chartData = $chartResponse->json();

                    if (isset($chartData['chart']['result'][0]['events']['dividends'])) {
                        $dividends = $chartData['chart']['result'][0]['events']['dividends'];
                        $dividendHistory = [];

                        foreach ($dividends as $timestamp => $dividend) {
                            $dividendHistory[] = [
                                'date' => Carbon::createFromTimestamp($timestamp)->format('Y-m-d'),
                                'amount' => $dividend['amount'],
                            ];
                        }

                        return ['history' => $dividendHistory];
                    }
                }
            } catch (\Exception $e) {
                Log::error("Yahoo Finance dividend fetch failed for {$symbol}: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /**
     * Update dividend information for a stock
     */
    public function updateDividendData(Stock $stock): bool
    {
        $dividendData = $this->fetchDividendDataFromYahoo($stock);

        if (!$dividendData) {
            return false;
        }

        // Process upcoming dividend
        if (
            isset($dividendData['upcoming']['ex_dividend_date']) &&
            isset($dividendData['current']['dividend_rate'])
        ) {

            $exDividendDate = $dividendData['upcoming']['ex_dividend_date'];
            $dividendDate = $dividendData['upcoming']['dividend_date'] ??
                Carbon::parse($exDividendDate)->addDays(7)->format('Y-m-d');
            $dividendAmount = $dividendData['current']['dividend_rate'];

            // Check if this dividend already exists
            $existingDividend = DividendPayment::where('stock_id', $stock->id)
                ->where('ex_dividend_date', $exDividendDate)
                ->first();

            if (!$existingDividend && $dividendAmount > 0) {
                DividendPayment::create([
                    'stock_id' => $stock->id,
                    'ex_dividend_date' => $exDividendDate,
                    'dividend_date' => $dividendDate,
                    'dividend_amount' => $dividendAmount,
                    'dividend_type' => 'regular',
                    'announcement_details' => json_encode($dividendData),
                ]);
            }
        }

        // Process historical dividends
        if (isset($dividendData['history'])) {
            foreach ($dividendData['history'] as $dividend) {
                $existingDividend = DividendPayment::where('stock_id', $stock->id)
                    ->where('ex_dividend_date', $dividend['date'])
                    ->first();

                if (!$existingDividend && $dividend['amount'] > 0) {
                    DividendPayment::create([
                        'stock_id' => $stock->id,
                        'ex_dividend_date' => $dividend['date'],
                        'dividend_date' => Carbon::parse($dividend['date'])->addDays(7)->format('Y-m-d'),
                        'dividend_amount' => $dividend['amount'],
                        'dividend_type' => 'regular',
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * Calculate dividend eligibility for a user's holdings
     */
    public function calculateDividendEligibility(int $userId, int $stockId): array
    {
        $stock = Stock::findOrFail($stockId);

        // Get all dividend payments for this stock
        $dividendPayments = $stock->dividendPayments()
            ->where('ex_dividend_date', '>=', Carbon::now()->subYear())
            ->orderBy('ex_dividend_date', 'desc')
            ->get();

        $eligibilityData = [];

        foreach ($dividendPayments as $dividend) {
            // Calculate holdings on ex-dividend date
            $holdingsOnExDate = $this->calculateHoldingsOnDate($userId, $stockId, $dividend->ex_dividend_date);

            if ($holdingsOnExDate > 0) {
                $totalDividendAmount = $holdingsOnExDate * $dividend->dividend_amount;

                // Check if user dividend record already exists
                $userRecord = UserDividendRecord::where('user_id', $userId)
                    ->where('dividend_payment_id', $dividend->id)
                    ->first();

                if (!$userRecord) {
                    // Create new user dividend record
                    $userRecord = UserDividendRecord::create([
                        'user_id' => $userId,
                        'stock_id' => $stockId,
                        'dividend_payment_id' => $dividend->id,
                        'qualifying_shares' => $holdingsOnExDate,
                        'total_dividend_amount' => $totalDividendAmount,
                        'record_date' => $dividend->ex_dividend_date,
                        'status' => $dividend->dividend_date <= Carbon::now() ? 'received' : 'qualified',
                    ]);
                } else {
                    // Update existing record status if dividend date has passed
                    if ($dividend->dividend_date <= Carbon::now() && $userRecord->status === 'qualified') {
                        $userRecord->update(['status' => 'received']);
                    }
                }

                $eligibilityData[] = [
                    'dividend_payment' => $dividend,
                    'user_record' => $userRecord,
                    'qualifying_shares' => $holdingsOnExDate,
                    'total_amount' => $totalDividendAmount,
                ];
            }
        }

        return $eligibilityData;
    }

    /**
     * Calculate holdings on a specific date
     */
    private function calculateHoldingsOnDate(int $userId, int $stockId, string $date): int
    {
        $transactions = StockTransaction::where('user_id', $userId)
            ->where('stock_id', $stockId)
            ->where('transaction_date', '<=', $date)
            ->orderBy('transaction_date')
            ->get();

        $totalQuantity = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                $totalQuantity += $transaction->quantity;
            } elseif ($transaction->transaction_type === 'sell') {
                $totalQuantity -= $transaction->quantity;
            }
        }

        return max(0, $totalQuantity);
    }

    /**
     * Get dividend summary for a user's portfolio
     */
    public function getUserDividendSummary(int $userId): array
    {
        $userDividendRecords = UserDividendRecord::with(['stock', 'dividendPayment'])
            ->where('user_id', $userId)
            ->orderBy('record_date', 'desc')
            ->get();

        $summary = [
            'total_qualified_amount' => 0,
            'total_received_amount' => 0,
            'pending_amount' => 0,
            'upcoming_dividends' => [],
            'recent_dividends' => [],
        ];

        foreach ($userDividendRecords as $record) {
            // Update status if dividend date has passed
            if ($record->dividendPayment->dividend_date <= Carbon::now() && $record->status === 'qualified') {
                $record->update(['status' => 'received']);
                $record->refresh();
            }

            $summary['total_qualified_amount'] += $record->total_dividend_amount;

            if ($record->status === 'received' || $record->status === 'credited') {
                $summary['total_received_amount'] += $record->total_dividend_amount;
            } else {
                $summary['pending_amount'] += $record->total_dividend_amount;

                if ($record->dividendPayment->dividend_date > Carbon::now()) {
                    $summary['upcoming_dividends'][] = $record;
                }
            }

            if ($record->record_date >= Carbon::now()->subMonths(3)) {
                $summary['recent_dividends'][] = $record;
            }
        }

        return $summary;
    }

    /**
     * Update ROI calculations to include dividends
     */
    public function getROIWithDividends(int $userId, int $stockId): array
    {
        $stock = Stock::findOrFail($stockId);
        $transactions = StockTransaction::where('user_id', $userId)
            ->where('stock_id', $stockId)
            ->get();

        // Calculate basic investment metrics - use current holdings investment, not total historical
        $buyTransactions = $transactions->where('transaction_type', 'buy');
        $sellTransactions = $transactions->where('transaction_type', 'sell');

        // Calculate current holdings using FIFO
        $buyQueue = collect();
        $allTransactionsSorted = $transactions->sortBy(['transaction_date', 'id']);

        foreach ($allTransactionsSorted as $transaction) {
            if ($transaction->transaction_type === 'buy' || $transaction->transaction_type === 'bonus') {
                $buyQueue->push([
                    'transaction' => $transaction,
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

        // Calculate current holdings investment
        $totalInvestment = $buyQueue->sum(function ($buyEntry) {
            $transaction = $buyEntry['transaction'];
            $remainingQuantity = $buyEntry['remaining'];
            return ($transaction->net_amount / $transaction->quantity) * $remainingQuantity;
        });

        $currentQuantity = $buyQueue->sum('remaining');
        $currentValue = $currentQuantity * $stock->current_price;

        // Calculate dividends received
        $dividendRecords = UserDividendRecord::where('user_id', $userId)
            ->where('stock_id', $stockId)
            ->get();

        $totalDividends = $dividendRecords->where('status', 'received')->sum('total_dividend_amount');
        $pendingDividends = $dividendRecords->where('status', 'qualified')->sum('total_dividend_amount');

        // Calculate ROI including dividends
        $totalReturns = ($currentValue - $totalInvestment) + $totalDividends;
        $roiPercent = $totalInvestment > 0 ? ($totalReturns / $totalInvestment) * 100 : 0;

        return [
            'basic_roi' => $totalInvestment > 0 ? (($currentValue - $totalInvestment) / $totalInvestment) * 100 : 0,
            'dividend_adjusted_roi' => $roiPercent,
            'total_dividends_received' => $totalDividends,
            'pending_dividends' => $pendingDividends,
            'dividend_yield' => $totalInvestment > 0 ? ($totalDividends / $totalInvestment) * 100 : 0,
        ];
    }
}
