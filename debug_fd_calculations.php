<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FixedDeposit;

echo "=== Fixed Deposit Data Analysis ===\n";
$fds = FixedDeposit::all();

echo "Total FDs: " . $fds->count() . "\n\n";

// Group by bank
$bankData = [];
foreach ($fds as $fd) {
    if (!isset($bankData[$fd->bank])) {
        $bankData[$fd->bank] = [
            'count' => 0,
            'principal' => 0,
            'interest' => 0,
            'maturity' => 0,
            'details' => []
        ];
    }
    $bankData[$fd->bank]['count']++;
    $bankData[$fd->bank]['principal'] += $fd->principal_amt;
    $bankData[$fd->bank]['interest'] += $fd->Int_amt;
    $bankData[$fd->bank]['maturity'] += $fd->maturity_amt;
    $bankData[$fd->bank]['details'][] = [
        'id' => $fd->id,
        'principal' => $fd->principal_amt,
        'interest' => $fd->Int_amt,
        'maturity' => $fd->maturity_amt,
        'start_date' => $fd->start_date,
        'maturity_date' => $fd->maturity_date
    ];
}

foreach ($bankData as $bank => $data) {
    echo "Bank: $bank\n";
    echo "Count: {$data['count']}\n";
    echo "Total Principal: " . number_format($data['principal'], 2) . "\n";
    echo "Total Interest: " . number_format($data['interest'], 2) . "\n";
    echo "Total Maturity: " . number_format($data['maturity'], 2) . "\n";
    echo "Details:\n";
    foreach ($data['details'] as $detail) {
        echo "  ID: {$detail['id']}, Principal: " . number_format($detail['principal'], 2) . 
             ", Interest: " . number_format($detail['interest'], 2) . 
             ", Maturity: " . number_format($detail['maturity'], 2) . 
             ", Start: {$detail['start_date']}, End: {$detail['maturity_date']}\n";
    }
    echo "\n";
}

echo "=== Dashboard Controller Logic Test ===\n";
// Test the dashboard controller logic
use Carbon\Carbon;

$calculationDate = Carbon::now();
$fixedDeposits = FixedDeposit::where('start_date', '<=', $calculationDate)
    ->where(function ($query) use ($calculationDate) {
        $query->where('maturity_date', '>=', $calculationDate)
              ->orWhere('closed', false);
    })
    ->get();

$totalPrincipal = $fixedDeposits->sum('principal_amt');
$totalUnrealizedInterest = 0;
$bankWiseData = [];

foreach ($fixedDeposits as $fd) {
    $unrealizedInterest = $fd->Int_amt;
    $totalUnrealizedInterest += $unrealizedInterest;
    
    if (!isset($bankWiseData[$fd->bank])) {
        $bankWiseData[$fd->bank] = [
            'principal' => 0,
            'interest' => 0
        ];
    }
    
    $bankWiseData[$fd->bank]['principal'] += $fd->principal_amt;
    $bankWiseData[$fd->bank]['interest'] += $unrealizedInterest;
}

echo "Dashboard Logic Results:\n";
echo "Total Principal: " . number_format($totalPrincipal, 2) . "\n";
echo "Total Interest: " . number_format($totalUnrealizedInterest, 2) . "\n";
echo "Bank-wise Data:\n";
foreach ($bankWiseData as $bank => $data) {
    echo "  $bank: Principal = " . number_format($data['principal'], 2) . 
         ", Interest = " . number_format($data['interest'], 2) . "\n";
}