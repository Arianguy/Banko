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
        ];

        foreach ($mutualFunds as $fund) {
            MutualFund::create($fund);
        }
    }
}
