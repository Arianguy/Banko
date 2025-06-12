<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DividendPayment extends Model
{
    protected $fillable = [
        'stock_id',
        'ex_dividend_date',
        'dividend_date',
        'dividend_amount',
        'dividend_type',
        'announcement_details',
    ];

    protected $casts = [
        'ex_dividend_date' => 'date',
        'dividend_date' => 'date',
        'dividend_amount' => 'decimal:4',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function userDividendRecords(): HasMany
    {
        return $this->hasMany(UserDividendRecord::class);
    }
}
