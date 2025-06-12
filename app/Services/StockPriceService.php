<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StockPriceService
{
    private string $apiKey;
    private string $baseUrl = 'https://www.alphavantage.co/query';

    public function __construct()
    {
        $this->apiKey = config('services.alpha_vantage.api_key', env('ALPHA_VANTAGE_API_KEY'));
    }

    /**
     * Get current stock price and related data
     */
    public function getStockQuote(string $symbol): ?array
    {
        if (!$this->apiKey) {
            Log::error('Alpha Vantage API key not configured');
            return null;
        }

        // Cache the result for 5 minutes to avoid excessive API calls
        $cacheKey = "stock_quote_{$symbol}";

        return Cache::remember($cacheKey, 300, function () use ($symbol) {
            return $this->fetchStockQuote($symbol);
        });
    }

    /**
     * Get multiple stock quotes
     */
    public function getMultipleQuotes(array $symbols): array
    {
        $quotes = [];

        foreach ($symbols as $symbol) {
            $quote = $this->getStockQuote($symbol);
            if ($quote) {
                $quotes[$symbol] = $quote;
            }

            // Add small delay to respect rate limits
            usleep(500000); // 0.5 second delay
        }

        return $quotes;
    }

    /**
     * Search for stocks by keyword
     */
    public function searchStocks(string $keyword): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $response = Http::get($this->baseUrl, [
                'function' => 'SYMBOL_SEARCH',
                'keywords' => $keyword,
                'apikey' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['bestMatches'])) {
                    return collect($data['bestMatches'])
                        ->filter(function ($match) {
                            // Filter for Indian stocks (NSE/BSE)
                            return str_contains($match['4. region'], 'India') ||
                                str_ends_with($match['1. symbol'], '.NS') ||
                                str_ends_with($match['1. symbol'], '.BO');
                        })
                        ->map(function ($match) {
                            return [
                                'symbol' => $match['1. symbol'],
                                'name' => $match['2. name'],
                                'type' => $match['3. type'],
                                'region' => $match['4. region'],
                                'currency' => $match['8. currency'],
                            ];
                        })
                        ->values()
                        ->toArray();
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to search stocks: " . $e->getMessage());
        }

        return null;
    }

    private function fetchStockQuote(string $symbol): ?array
    {
        // Try different symbol formats for Indian stocks
        $symbols = [
            $symbol,
            $symbol . '.NS',  // NSE
            $symbol . '.BO',  // BSE
        ];

        foreach ($symbols as $symbolVariant) {
            try {
                // Try Global Quote first
                $response = Http::get($this->baseUrl, [
                    'function' => 'GLOBAL_QUOTE',
                    'symbol' => $symbolVariant,
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['Global Quote']) && !empty($data['Global Quote'])) {
                        $quote = $data['Global Quote'];

                        $price = floatval($quote['05. price'] ?? 0);

                        if ($price > 0) {
                            return [
                                'symbol' => $symbolVariant,
                                'price' => $price,
                                'change' => floatval($quote['09. change'] ?? 0),
                                'change_percent' => floatval(str_replace('%', '', $quote['10. change percent'] ?? '0')),
                                'volume' => intval($quote['06. volume'] ?? 0),
                                'previous_close' => floatval($quote['08. previous close'] ?? 0),
                                'open' => floatval($quote['02. open'] ?? 0),
                                'high' => floatval($quote['03. high'] ?? 0),
                                'low' => floatval($quote['04. low'] ?? 0),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                // If Global Quote fails, try TIME_SERIES_INTRADAY
                $response = Http::get($this->baseUrl, [
                    'function' => 'TIME_SERIES_INTRADAY',
                    'symbol' => $symbolVariant,
                    'interval' => '5min',
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['Time Series (5min)']) && !empty($data['Time Series (5min)'])) {
                        $timeSeries = $data['Time Series (5min)'];
                        $latestTime = array_key_first($timeSeries);
                        $latestData = $timeSeries[$latestTime];

                        $price = floatval($latestData['4. close'] ?? 0);

                        if ($price > 0) {
                            return [
                                'symbol' => $symbolVariant,
                                'price' => $price,
                                'open' => floatval($latestData['1. open'] ?? 0),
                                'high' => floatval($latestData['2. high'] ?? 0),
                                'low' => floatval($latestData['3. low'] ?? 0),
                                'volume' => intval($latestData['5. volume'] ?? 0),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to fetch quote for {$symbolVariant}: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }
}
