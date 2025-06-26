<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateStockPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock-prices:update {--stock= : Update prices for specific stock ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update stock prices from multiple APIs with fallback';

    private $successCount = 0;
    private $failureCount = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stockId = $this->option('stock');

        if ($stockId) {
            // Update specific stock
            $stock = Stock::find($stockId);
            if (!$stock) {
                $this->error("Stock with ID {$stockId} not found");
                return 1;
            }
            $stocks = collect([$stock]);
        } else {
            // Get all unique stocks from transactions
            $stocks = Stock::all();
        }

        if ($stocks->isEmpty()) {
            $this->info('No stocks found to update');
            return 0;
        }

        $this->info("Updating prices for {$stocks->count()} stocks using multiple APIs...");
        $progressBar = $this->output->createProgressBar($stocks->count());

        foreach ($stocks as $stock) {
            try {
                $updated = $this->updateStockPriceWithFallback($stock);

                if ($updated) {
                    $this->successCount++;
                } else {
                    $this->failureCount++;
                }

                $progressBar->advance();

                // Small delay between requests
                usleep(500000); // 0.5 seconds

            } catch (\Exception $e) {
                Log::error("Failed to update price for {$stock->symbol}: " . $e->getMessage());
                $this->error("Failed to update {$stock->symbol}: " . $e->getMessage());
                $this->failureCount++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Successfully updated: {$this->successCount} stocks");
        $this->info("❌ Failed to update: {$this->failureCount} stocks");

        return 0;
    }

    private function updateStockPriceWithFallback(Stock $stock): bool
    {
        // Try different APIs in order (Yahoo first as it's more reliable and has no rate limits)
        $apis = [
            'yahoo',
            'alphavantage',
            'finnhub',
            'twelvedata'
        ];

        foreach ($apis as $api) {
            try {
                $result = $this->fetchFromAPI($stock, $api);
                if ($result) {
                    $this->line("✅ Updated {$stock->symbol}: ₹{$result['price']} (from {$api})");
                    return true;
                }
            } catch (\Exception $e) {
                $this->line("⚠️  {$api} failed for {$stock->symbol}: " . $e->getMessage());
                continue;
            }
        }

        $this->line("❌ All APIs failed for {$stock->symbol}");
        return false;
    }

    private function fetchFromAPI(Stock $stock, string $api): ?array
    {
        switch ($api) {
            case 'alphavantage':
                return $this->fetchFromAlphaVantage($stock);
            case 'yahoo':
                return $this->fetchFromYahoo($stock);
            case 'finnhub':
                return $this->fetchFromFinnhub($stock);
            case 'twelvedata':
                return $this->fetchFromTwelveData($stock);
            default:
                return null;
        }
    }

    private function fetchFromAlphaVantage(Stock $stock): ?array
    {
        $apiKey = env('ALPHA_VANTAGE_API_KEY');
        if (!$apiKey) {
            // Instead of throwing, just skip Alpha Vantage if not configured
            $this->line("⚠️  Alpha Vantage API key not found. Skipping Alpha Vantage for {$stock->symbol}.");
            return null;
        }

        $symbols = [$stock->symbol . '.NS', $stock->symbol . '.BO', $stock->symbol];

        foreach ($symbols as $symbol) {
            $response = Http::timeout(10)->get('https://www.alphavantage.co/query', [
                'function' => 'GLOBAL_QUOTE',
                'symbol' => $symbol,
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check for rate limit
                if (isset($data['Information']) && strpos($data['Information'], 'rate limit') !== false) {
                    throw new \Exception('Rate limit exceeded');
                }

                if (isset($data['Global Quote']) && !empty($data['Global Quote'])) {
                    $quote = $data['Global Quote'];
                    $currentPrice = floatval($quote['05. price'] ?? 0);
                    $change = floatval($quote['09. change'] ?? 0);
                    $changePercent = floatval(str_replace('%', '', $quote['10. change percent'] ?? '0'));

                    if ($currentPrice > 0) {
                        $stock->update([
                            'current_price' => $currentPrice,
                            'day_change' => $change,
                            'day_change_percent' => $changePercent,
                        ]);

                        return ['price' => $currentPrice, 'change' => $change, 'change_percent' => $changePercent];
                    }
                }
            }
        }

        throw new \Exception('No valid data found');
    }

    private function fetchFromYahoo(Stock $stock): ?array
    {
        // Yahoo Finance API (free, no key required)
        $symbols = [$stock->symbol . '.NS', $stock->symbol . '.BO'];

        foreach ($symbols as $symbol) {
            $response = Http::timeout(10)->get('https://query1.finance.yahoo.com/v8/finance/chart/' . $symbol);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                    $meta = $data['chart']['result'][0]['meta'];

                    $currentPrice = floatval($meta['regularMarketPrice']);
                    $previousClose = floatval($meta['previousClose'] ?? $currentPrice);

                    // Extract 52-week high/low data
                    $fiftyTwoWeekHigh = floatval($meta['fiftyTwoWeekHigh'] ?? 0);
                    $fiftyTwoWeekLow = floatval($meta['fiftyTwoWeekLow'] ?? 0);

                    $change = $currentPrice - $previousClose;
                    $changePercent = $previousClose > 0 ? ($change / $previousClose) * 100 : 0;

                    if ($currentPrice > 0) {
                        $updateData = [
                            'current_price' => $currentPrice,
                            'day_change' => $change,
                            'day_change_percent' => $changePercent,
                        ];

                        // Only update 52-week data if it's available and valid
                        if ($fiftyTwoWeekHigh > 0) {
                            $updateData['week_52_high'] = $fiftyTwoWeekHigh;
                        }
                        if ($fiftyTwoWeekLow > 0) {
                            $updateData['week_52_low'] = $fiftyTwoWeekLow;
                        }

                        $stock->update($updateData);

                        return [
                            'price' => $currentPrice,
                            'change' => $change,
                            'change_percent' => $changePercent,
                            'week_52_high' => $fiftyTwoWeekHigh,
                            'week_52_low' => $fiftyTwoWeekLow
                        ];
                    }
                }
            }
        }

        throw new \Exception('No valid data from Yahoo');
    }

    private function fetchFromFinnhub(Stock $stock): ?array
    {
        // Finnhub API (free tier: 60 calls/minute)
        $apiKey = env('FINNHUB_API_KEY', 'cr69rs9r01qm3vhcjlmgcr69rs9r01qm3vhcjln0'); // Free demo key

        $symbols = [$stock->symbol . '.NS', $stock->symbol . '.BO'];

        foreach ($symbols as $symbol) {
            $response = Http::timeout(10)->get('https://finnhub.io/api/v1/quote', [
                'symbol' => $symbol,
                'token' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['c']) && $data['c'] > 0) {
                    $currentPrice = floatval($data['c']); // Current price
                    $change = floatval($data['d'] ?? 0); // Change
                    $changePercent = floatval($data['dp'] ?? 0); // Change percent

                    $stock->update([
                        'current_price' => $currentPrice,
                        'day_change' => $change,
                        'day_change_percent' => $changePercent,
                    ]);

                    return ['price' => $currentPrice, 'change' => $change, 'change_percent' => $changePercent];
                }
            }
        }

        throw new \Exception('No valid data from Finnhub');
    }

    private function fetchFromTwelveData(Stock $stock): ?array
    {
        // Twelve Data API (free tier: 800 calls/day)
        $apiKey = env('TWELVE_DATA_API_KEY', 'demo'); // Use demo key or get free key

        $symbols = [$stock->symbol . '.NSE', $stock->symbol . '.BSE'];

        foreach ($symbols as $symbol) {
            $response = Http::timeout(10)->get('https://api.twelvedata.com/price', [
                'symbol' => $symbol,
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['price']) && floatval($data['price']) > 0) {
                    $currentPrice = floatval($data['price']);

                    $stock->update([
                        'current_price' => $currentPrice,
                    ]);

                    return ['price' => $currentPrice, 'change' => 0, 'change_percent' => 0];
                }
            }
        }

        throw new \Exception('No valid data from Twelve Data');
    }
}
