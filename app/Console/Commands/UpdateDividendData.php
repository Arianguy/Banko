<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\DividendService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateDividendData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dividends:update {--stock=* : Specific stock symbols to update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update dividend data from Yahoo Finance API for all stocks or specific stocks';

    private $dividendService;
    private $successCount = 0;
    private $failureCount = 0;

    public function __construct(DividendService $dividendService)
    {
        parent::__construct();
        $this->dividendService = $dividendService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stockSymbols = $this->option('stock');

        if (empty($stockSymbols)) {
            // Update all stocks
            $stocks = Stock::all();
            $this->info("Updating dividend data for all {$stocks->count()} stocks...");
        } else {
            // Update specific stocks
            $stocks = Stock::whereIn('symbol', $stockSymbols)->get();
            $this->info("Updating dividend data for " . count($stockSymbols) . " specified stocks...");
        }

        if ($stocks->isEmpty()) {
            $this->warn('No stocks found to update');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($stocks->count());
        $progressBar->start();

        foreach ($stocks as $stock) {
            try {
                $updated = $this->dividendService->updateDividendData($stock);

                if ($updated) {
                    $this->successCount++;
                    $this->line("\n✅ Updated dividend data for {$stock->symbol}");
                } else {
                    $this->failureCount++;
                    $this->line("\n⚠️  No dividend data found for {$stock->symbol}");
                }

                $progressBar->advance();

                // Add delay to avoid overwhelming the API
                usleep(500000); // 0.5 seconds

            } catch (\Exception $e) {
                $this->failureCount++;
                Log::error("Failed to update dividend data for {$stock->symbol}: " . $e->getMessage());
                $this->line("\n❌ Failed to update {$stock->symbol}: " . $e->getMessage());
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Successfully updated dividend data for: {$this->successCount} stocks");
        $this->info("❌ Failed or no data found for: {$this->failureCount} stocks");

        return 0;
    }
}
