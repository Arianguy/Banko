<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FixedDeposit;
use Carbon\Carbon;

echo "=== Testing Updated FD Calculations (Excluding Closed/Matured) ===\n\n";

// Test the updated dashboard controller logic
echo "1. Dashboard Controller Logic Test:\n";
$fixedDeposits = FixedDeposit::where('closed', false)
    ->where('matured', false)
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

echo "Active FDs Count: " . $fixedDeposits->count() . "\n";
echo "Total Principal: " . number_format($totalPrincipal, 2) . "\n";
echo "Total Interest: " . number_format($totalUnrealizedInterest, 2) . "\n";
echo "Bank-wise Data:\n";
foreach ($bankWiseData as $bank => $data) {
    echo "  $bank: Principal = " . number_format($data['principal'], 2) . 
         ", Interest = " . number_format($data['interest'], 2) . "\n";
}

echo "\n2. Comparison with All FDs (including closed/matured):\n";
$allFds = FixedDeposit::all();
$allTotalPrincipal = $allFds->sum('principal_amt');
$allTotalInterest = $allFds->sum('Int_amt');

echo "All FDs Count: " . $allFds->count() . "\n";
echo "All Total Principal: " . number_format($allTotalPrincipal, 2) . "\n";
echo "All Total Interest: " . number_format($allTotalInterest, 2) . "\n";

echo "\n3. Closed/Matured FDs:\n";
$closedMaturedFds = FixedDeposit::where(function($query) {
    $query->where('closed', true)->orWhere('matured', true);
})->get();

echo "Closed/Matured FDs Count: " . $closedMaturedFds->count() . "\n";
if ($closedMaturedFds->count() > 0) {
    echo "Closed/Matured FDs Details:\n";
    foreach ($closedMaturedFds as $fd) {
        $status = [];
        if ($fd->closed) $status[] = 'Closed';
        if ($fd->matured) $status[] = 'Matured';
        echo "  ID: {$fd->id}, Bank: {$fd->bank}, Principal: " . number_format($fd->principal_amt, 2) . 
             ", Status: " . implode(', ', $status) . "\n";
    }
    
    $closedMaturedPrincipal = $closedMaturedFds->sum('principal_amt');
    $closedMaturedInterest = $closedMaturedFds->sum('Int_amt');
    echo "Closed/Matured Total Principal: " . number_format($closedMaturedPrincipal, 2) . "\n";
    echo "Closed/Matured Total Interest: " . number_format($closedMaturedInterest, 2) . "\n";
}

echo "\n4. Verification:\n";
echo "Active Principal + Closed/Matured Principal = " . number_format($totalPrincipal + ($closedMaturedFds->sum('principal_amt') ?? 0), 2) . "\n";
echo "Should equal All Principal = " . number_format($allTotalPrincipal, 2) . "\n";
echo "Match: " . (abs(($totalPrincipal + ($closedMaturedFds->sum('principal_amt') ?? 0)) - $allTotalPrincipal) < 0.01 ? 'YES' : 'NO') . "\n";