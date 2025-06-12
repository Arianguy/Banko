<?php

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stocks = [
            [
                'symbol' => 'INFY',
                'name' => 'Infosys Limited',
                'exchange' => 'NSE',
                'sector' => 'Information Technology',
                'industry' => 'IT Services',
                'week_52_high' => 1680.00,
                'week_52_low' => 1350.00,
            ],
            [
                'symbol' => 'TCS',
                'name' => 'Tata Consultancy Services',
                'exchange' => 'NSE',
                'sector' => 'Information Technology',
                'industry' => 'IT Services',
                'week_52_high' => 3650.00,
                'week_52_low' => 3100.00,
            ],
            [
                'symbol' => 'RELIANCE',
                'name' => 'Reliance Industries Limited',
                'exchange' => 'NSE',
                'sector' => 'Oil & Gas',
                'industry' => 'Refineries',
                'week_52_high' => 1520.00,
                'week_52_low' => 1280.00,
            ],
            [
                'symbol' => 'HDFCBANK',
                'name' => 'HDFC Bank Limited',
                'exchange' => 'NSE',
                'sector' => 'Financial Services',
                'industry' => 'Private Banks',
                'week_52_high' => 2050.00,
                'week_52_low' => 1750.00,
            ],
            [
                'symbol' => 'ITC',
                'name' => 'ITC Limited',
                'exchange' => 'NSE',
                'sector' => 'FMCG',
                'industry' => 'Tobacco Products',
                'week_52_high' => 480.00,
                'week_52_low' => 380.00,
            ],
            [
                'symbol' => 'WIPRO',
                'name' => 'Wipro Limited',
                'exchange' => 'NSE',
                'sector' => 'Information Technology',
                'industry' => 'IT Services',
                'week_52_high' => 290.00,
                'week_52_low' => 230.00,
            ],
            [
                'symbol' => 'ADANIPOWER',
                'name' => 'Adani Power Limited',
                'exchange' => 'NSE',
                'sector' => 'Power',
                'industry' => 'Power Generation',
                'week_52_high' => 650.00,
                'week_52_low' => 420.00,
            ],
            // BSE versions for cross-exchange trading
            [
                'symbol' => 'ADANIPOWER',
                'name' => 'Adani Power Limited',
                'exchange' => 'BSE',
                'sector' => 'Power',
                'industry' => 'Power Generation',
                'week_52_high' => 650.00,
                'week_52_low' => 420.00,
            ],
        ];

        foreach ($stocks as $stockData) {
            Stock::updateOrCreate(
                [
                    'symbol' => $stockData['symbol'],
                    'exchange' => $stockData['exchange']
                ],
                $stockData
            );
        }
    }
}
