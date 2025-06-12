<?php

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdditionalStocksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stocks = [
            [
                'symbol' => 'BANKBARODA',
                'name' => 'Bank of Baroda',
                'exchange' => 'NSE',
                'sector' => 'Financial Services',
                'industry' => 'Public Banks',
            ],
            [
                'symbol' => 'CANBK',
                'name' => 'Canara Bank',
                'exchange' => 'NSE',
                'sector' => 'Financial Services',
                'industry' => 'Public Banks',
            ],
            [
                'symbol' => 'COALINDIA',
                'name' => 'Coal India Limited',
                'exchange' => 'NSE',
                'sector' => 'Oil & Gas',
                'industry' => 'Coal',
            ],
            [
                'symbol' => 'FEDERALBNK',
                'name' => 'Federal Bank Limited',
                'exchange' => 'NSE',
                'sector' => 'Financial Services',
                'industry' => 'Private Banks',
            ],
            [
                'symbol' => 'GAIL',
                'name' => 'GAIL (India) Limited',
                'exchange' => 'NSE',
                'sector' => 'Oil & Gas',
                'industry' => 'Gas Distribution',
            ],
            [
                'symbol' => 'GUJTLRM',
                'name' => 'Gujarat Toolroom Limited',
                'exchange' => 'NSE',
                'sector' => 'Industrial Manufacturing',
                'industry' => 'Engineering',
            ],
            [
                'symbol' => 'GULPOLY',
                'name' => 'Gujarat Poly Electronics Limited',
                'exchange' => 'NSE',
                'sector' => 'Technology',
                'industry' => 'Electronics',
            ],
            [
                'symbol' => 'IEX',
                'name' => 'Indian Energy Exchange Limited',
                'exchange' => 'NSE',
                'sector' => 'Financial Services',
                'industry' => 'Exchange',
            ],
            [
                'symbol' => 'JIOFIN',
                'name' => 'Jio Financial Services Limited',
                'exchange' => 'NSE',
                'sector' => 'Financial Services',
                'industry' => 'Financial Services',
            ],
            [
                'symbol' => 'LICHSGFIN',
                'name' => 'LIC Housing Finance Limited',
                'exchange' => 'NSE',
                'sector' => 'Financial Services',
                'industry' => 'Housing Finance',
            ],
            [
                'symbol' => 'LTFOODS',
                'name' => 'LT Foods Limited',
                'exchange' => 'NSE',
                'sector' => 'FMCG',
                'industry' => 'Food Products',
            ],
            [
                'symbol' => 'NATIONALUM',
                'name' => 'National Aluminium Company Limited',
                'exchange' => 'NSE',
                'sector' => 'Metals & Mining',
                'industry' => 'Aluminium',
            ],
            [
                'symbol' => 'NAVA',
                'name' => 'Nava Limited',
                'exchange' => 'NSE',
                'sector' => 'Metals & Mining',
                'industry' => 'Iron & Steel',
            ],
            [
                'symbol' => 'NMDC',
                'name' => 'NMDC Limited',
                'exchange' => 'NSE',
                'sector' => 'Metals & Mining',
                'industry' => 'Iron Ore',
            ],
            [
                'symbol' => 'NSLNISP',
                'name' => 'NSIL Limited',
                'exchange' => 'NSE',
                'sector' => 'Technology',
                'industry' => 'Space Technology',
            ],
            [
                'symbol' => 'NTPC',
                'name' => 'NTPC Limited',
                'exchange' => 'NSE',
                'sector' => 'Power',
                'industry' => 'Power Generation',
            ],
            [
                'symbol' => 'ONGC',
                'name' => 'Oil and Natural Gas Corporation Limited',
                'exchange' => 'NSE',
                'sector' => 'Oil & Gas',
                'industry' => 'Oil Exploration',
            ],
            [
                'symbol' => 'PAYTM',
                'name' => 'One 97 Communications Limited',
                'exchange' => 'NSE',
                'sector' => 'Technology',
                'industry' => 'Fintech',
            ],
            [
                'symbol' => 'POWERGRID',
                'name' => 'Power Grid Corporation of India Limited',
                'exchange' => 'NSE',
                'sector' => 'Power',
                'industry' => 'Power Transmission',
            ],
            [
                'symbol' => 'SAIL',
                'name' => 'Steel Authority of India Limited',
                'exchange' => 'NSE',
                'sector' => 'Metals & Mining',
                'industry' => 'Iron & Steel',
            ],
            [
                'symbol' => 'SUZLON',
                'name' => 'Suzlon Energy Limited',
                'exchange' => 'NSE',
                'sector' => 'Power',
                'industry' => 'Renewable Energy',
            ],
            [
                'symbol' => 'TATACONSUM',
                'name' => 'Tata Consumer Products Limited',
                'exchange' => 'NSE',
                'sector' => 'FMCG',
                'industry' => 'Consumer Products',
            ],
            [
                'symbol' => 'TATAPOWER',
                'name' => 'Tata Power Company Limited',
                'exchange' => 'NSE',
                'sector' => 'Power',
                'industry' => 'Power Generation',
            ],
            [
                'symbol' => 'VBL',
                'name' => 'Varun Beverages Limited',
                'exchange' => 'NSE',
                'sector' => 'FMCG',
                'industry' => 'Beverages',
            ],
            [
                'symbol' => 'VEDL',
                'name' => 'Vedanta Limited',
                'exchange' => 'NSE',
                'sector' => 'Metals & Mining',
                'industry' => 'Diversified Mining',
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

        $this->command->info('Successfully imported ' . count($stocks) . ' additional stocks!');
    }
}
