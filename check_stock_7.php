<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Stock;

$stock = Stock::find(7);
if ($stock) {
    echo "Stock ID 7: {$stock->symbol} - {$stock->name}\n";
} else {
    echo "Stock ID 7: Not found\n";
}

$stock8 = Stock::find(8);
if ($stock8) {
    echo "Stock ID 8: {$stock8->symbol} - {$stock8->name}\n";
} else {
    echo "Stock ID 8: Not found\n";
}