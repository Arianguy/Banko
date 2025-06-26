<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Stock;
use App\Services\DividendService;

class RefreshDividendEligibility extends Command
{
    protected $signature = 'dividends:refresh-eligibility';
    protected $description = 'Refresh dividend eligibility calculations for all users';

    public function handle()
    {
        $this->info('Refreshing dividend eligibility calculations...');

        $dividendService = app(DividendService::class);
        $users = User::all();
        $totalCalculations = 0;

        foreach ($users as $user) {
            $this->info("Processing user: {$user->email}");

            // Get all stocks the user has transactions for
            $userStocks = Stock::whereHas('transactions', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            foreach ($userStocks as $stock) {
                try {
                    $dividendService->calculateDividendEligibility($user->id, $stock->id);
                    $totalCalculations++;
                } catch (\Exception $e) {
                    $this->error("Error processing {$stock->symbol} for user {$user->email}: " . $e->getMessage());
                }
            }
        }

        $this->info("Completed! Processed {$totalCalculations} stock-user combinations.");

        return 0;
    }
}
