<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Stock Transactions Table Structure ===\n\n";

$columns = Schema::getColumnListing('stock_transactions');
echo "Columns in stock_transactions table:\n";
foreach($columns as $column) {
    echo "- {$column}\n";
}

echo "\n=== Sample Transaction Data ===\n";
$transaction = DB::table('stock_transactions')->first();
if ($transaction) {
    foreach ($transaction as $key => $value) {
        echo "{$key}: {$value}\n";
    }
} else {
    echo "No transactions found.\n";
}