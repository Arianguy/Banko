<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MutualFund;
use App\Models\MutualFundTransaction;

echo "=== Checking for HDFC Mutual Funds ===\n";
$hdfcFunds = MutualFund::where('scheme_name', 'LIKE', '%HDFC%')
    ->orWhere('fund_house', 'LIKE', '%HDFC%')
    ->get();

foreach ($hdfcFunds as $fund) {
    echo "Fund ID: {$fund->id}, Name: {$fund->scheme_name}, House: {$fund->fund_house}\n";
}

echo "\n=== Checking for HDFC Transactions ===\n";
$hdfcTransactions = MutualFundTransaction::with('mutualFund')
    ->whereHas('mutualFund', function($query) {
        $query->where('scheme_name', 'LIKE', '%HDFC%')
              ->orWhere('fund_house', 'LIKE', '%HDFC%');
    })
    ->get();

foreach ($hdfcTransactions as $transaction) {
    echo "Transaction ID: {$transaction->id}, Fund: {$transaction->mutualFund->scheme_name}, Type: {$transaction->transaction_type}, Units: {$transaction->units}, Date: {$transaction->transaction_date}\n";
}

echo "\n=== All Transactions Summary ===\n";
$allTransactions = MutualFundTransaction::with('mutualFund')->get();
foreach ($allTransactions as $transaction) {
    echo "ID: {$transaction->id}, Fund: {$transaction->mutualFund->scheme_name}, Type: {$transaction->transaction_type}, Units: {$transaction->units}\n";
}