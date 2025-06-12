<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'stock_id',
        'transaction_type',
        'quantity',
        'price_per_stock',
        'total_amount',
        'transaction_date',
        'exchange',
        'broker',
        'brokerage',
        'total_charges',
        'net_amount',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'price_per_stock' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'brokerage' => 'decimal:2',
        'total_charges' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
