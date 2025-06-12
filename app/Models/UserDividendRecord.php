<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDividendRecord extends Model
{
    protected $fillable = [
        'user_id',
        'stock_id',
        'dividend_payment_id',
        'qualifying_shares',
        'total_dividend_amount',
        'record_date',
        'status',
        'received_date',
        'notes',
    ];

    protected $casts = [
        'record_date' => 'date',
        'received_date' => 'date',
        'total_dividend_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function dividendPayment(): BelongsTo
    {
        return $this->belongsTo(DividendPayment::class);
    }
}
