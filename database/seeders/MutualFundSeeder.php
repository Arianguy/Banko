<?php

namespace Database\Seeders;

use App\Models\MutualFund;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MutualFundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mutualFunds = [
            [
                'scheme_code' => 'HDFC-EQ-001',
                'scheme_name' => 'HDFC Equity Fund - Direct Plan - Growth',
                'fund_house' => 'HDFC Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Large Cap',
                'current_nav' => 856.45,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 1.05,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'SBI-BLUE-002',
                'scheme_name' => 'SBI Blue Chip Fund - Direct Plan - Growth',
                'fund_house' => 'SBI Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Large Cap',
                'current_nav' => 67.89,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 0.98,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'ICICI-TECH-003',
                'scheme_name' => 'ICICI Prudential Technology Fund - Direct Plan - Growth',
                'fund_house' => 'ICICI Prudential Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Sectoral/Thematic',
                'current_nav' => 145.67,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 1.25,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'AXIS-SMALL-004',
                'scheme_name' => 'Axis Small Cap Fund - Direct Plan - Growth',
                'fund_house' => 'Axis Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Small Cap',
                'current_nav' => 78.34,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 1.35,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'UTI-NIFTY-005',
                'scheme_name' => 'UTI Nifty Index Fund - Direct Plan - Growth',
                'fund_house' => 'UTI Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Index Fund',
                'current_nav' => 234.56,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 0.20,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'KOTAK-DEBT-006',
                'scheme_name' => 'Kotak Corporate Bond Fund - Direct Plan - Growth',
                'fund_house' => 'Kotak Mahindra Mutual Fund',
                'category' => 'Debt',
                'sub_category' => 'Corporate Bond',
                'current_nav' => 12.45,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 0.45,
                'fund_type' => 'debt',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'FRANKLIN-HYBRID-007',
                'scheme_name' => 'Franklin India Balanced Advantage Fund - Direct Plan - Growth',
                'fund_house' => 'Franklin Templeton Mutual Fund',
                'category' => 'Hybrid',
                'sub_category' => 'Dynamic Asset Allocation',
                'current_nav' => 89.12,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 1.15,
                'fund_type' => 'hybrid',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'MIRAE-EMERGING-008',
                'scheme_name' => 'Mirae Asset Emerging Bluechip Fund - Direct Plan - Growth',
                'fund_house' => 'Mirae Asset Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Large & Mid Cap',
                'current_nav' => 123.78,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 1.05,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => 'HDFC-FLEXI-009',
                'scheme_name' => 'HDFC Flexi Cap Direct Plan Growth',
                'fund_house' => 'HDFC Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Flexi Cap',
                'current_nav' => 2174.26,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 0.69,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => '118269',
                'scheme_name' => 'Canara Robeco Large Cap Fund - Direct Plan - Growth',
                'fund_house' => 'Canara Robeco Mutual Fund',
                'category' => 'Equity',
                'sub_category' => 'Large Cap',
                'current_nav' => 73.58,
                'nav_date' => Carbon::now()->subDays(1),
                'expense_ratio' => 0.45,
                'fund_type' => 'equity',
                'is_active' => true,
            ],
            [
                'scheme_code' => '130503',
                'scheme_name' => 'HDFC Small Cap Fund - Direct Plan - Growth',
                'fund_house' => 'HDFC Asset Management Company Limited',
                'category' => 'Equity',
                'sub_category' => 'Small Cap',
                'current_nav' => 165.87,
                'nav_date' => now()->format('Y-m-d'),
                'expense_ratio' => 0.69,
                'fund_type' => 'Direct',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scheme_code' => 'MOTILAL-VALUE-012',
                'scheme_name' => 'Motilal Oswal BSE Enhanced Value Index Fund - Direct Plan - Growth',
                'fund_house' => 'Motilal Oswal Asset Management Company Limited',
                'category' => 'Equity',
                'sub_category' => 'Index Fund',
                'current_nav' => 25.091,
                'nav_date' => now()->format('Y-m-d'),
                'expense_ratio' => 0.20,
                'fund_type' => 'Direct',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scheme_code' => 'PGIM-MIDCAP-013',
                'scheme_name' => 'PGIM India Midcap Opportunities Fund - Direct Plan - Growth',
                'fund_house' => 'PGIM India Asset Management Private Limited',
                'category' => 'Equity',
                'sub_category' => 'Mid Cap',
                'current_nav' => 75.89,
                'nav_date' => now()->format('Y-m-d'),
                'expense_ratio' => 0.44,
                'fund_type' => 'Direct',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scheme_code' => 'UTI-MOMENTUM-014',
                'scheme_name' => 'UTI Nifty 200 Momentum 30 Index Fund - Direct Plan - Growth',
                'fund_house' => 'UTI Asset Management Company Limited',
                'category' => 'Equity',
                'sub_category' => 'Index Fund',
                'current_nav' => 21.4592,
                'nav_date' => now()->format('Y-m-d'),
                'expense_ratio' => 0.45,
                'fund_type' => 'Direct',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($mutualFunds as $fund) {
            MutualFund::updateOrCreate(
                ['scheme_code' => $fund['scheme_code']],
                $fund
            );
        }
    }
}
