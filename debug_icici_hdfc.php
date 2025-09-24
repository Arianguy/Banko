<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FixedDeposit;

echo "=== ICICI Fixed Deposit Analysis ===\n";
$iciciFds = FixedDeposit::where('bank', 'ICICI')->get();

echo "ICICI FDs Count: " . $iciciFds->count() . "\n";
$totalPrincipal = 0;
$totalInterest = 0;

foreach ($iciciFds as $fd) {
    echo "ID: {$fd->id}\n";
    echo "  Principal: " . number_format($fd->principal_amt, 2) . "\n";
    echo "  Interest: " . number_format($fd->Int_amt, 2) . "\n";
    echo "  Maturity: " . number_format($fd->maturity_amt, 2) . "\n";
    echo "  Start Date: {$fd->start_date}\n";
    echo "  Maturity Date: {$fd->maturity_date}\n";
    echo "  Rate: {$fd->int_rate}%\n";
    echo "  Term: {$fd->term} days\n";
    echo "  Account: {$fd->accountno}\n";
    echo "  Closed: " . ($fd->closed ? 'Yes' : 'No') . "\n";
    echo "  Matured: " . ($fd->matured ? 'Yes' : 'No') . "\n";
    echo "  ---\n";
    
    $totalPrincipal += $fd->principal_amt;
    $totalInterest += $fd->Int_amt;
}

echo "ICICI Total Principal: " . number_format($totalPrincipal, 2) . "\n";
echo "ICICI Total Interest: " . number_format($totalInterest, 2) . "\n";

echo "\n=== HDFC Fixed Deposit Analysis ===\n";
$hdfcFds = FixedDeposit::where('bank', 'HDFC')->get();

echo "HDFC FDs Count: " . $hdfcFds->count() . "\n";
$totalPrincipalHdfc = 0;
$totalInterestHdfc = 0;

foreach ($hdfcFds as $fd) {
    echo "ID: {$fd->id}\n";
    echo "  Principal: " . number_format($fd->principal_amt, 2) . "\n";
    echo "  Interest: " . number_format($fd->Int_amt, 2) . "\n";
    echo "  Maturity: " . number_format($fd->maturity_amt, 2) . "\n";
    echo "  Start Date: {$fd->start_date}\n";
    echo "  Maturity Date: {$fd->maturity_date}\n";
    echo "  Rate: {$fd->int_rate}%\n";
    echo "  Term: {$fd->term} days\n";
    echo "  Account: {$fd->accountno}\n";
    echo "  Closed: " . ($fd->closed ? 'Yes' : 'No') . "\n";
    echo "  Matured: " . ($fd->matured ? 'Yes' : 'No') . "\n";
    echo "  ---\n";
    
    $totalPrincipalHdfc += $fd->principal_amt;
    $totalInterestHdfc += $fd->Int_amt;
}

echo "HDFC Total Principal: " . number_format($totalPrincipalHdfc, 2) . "\n";
echo "HDFC Total Interest: " . number_format($totalInterestHdfc, 2) . "\n";

echo "\n=== Expected vs Actual ===\n";
echo "ICICI Expected Principal: 25,74,000 (2,574,000)\n";
echo "ICICI Actual Principal: " . number_format($totalPrincipal, 2) . "\n";
echo "ICICI Difference: " . number_format($totalPrincipal - 2574000, 2) . "\n";

echo "\nHDFC Expected Interest: 3,54,538 (354,538)\n";
echo "HDFC Actual Interest: " . number_format($totalInterestHdfc, 2) . "\n";
echo "HDFC Difference: " . number_format($totalInterestHdfc - 354538, 2) . "\n";