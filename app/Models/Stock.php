<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'exchange',
        'sector',
        'industry',
        'current_price',
        'day_change',
        'day_change_percent',
        'week_52_high',
        'week_52_low',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'day_change' => 'decimal:2',
        'day_change_percent' => 'decimal:2',
        'week_52_high' => 'decimal:2',
        'week_52_low' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function userTransactions($userId): HasMany
    {
        return $this->hasMany(StockTransaction::class)->where('user_id', $userId);
    }

    public function dividendPayments(): HasMany
    {
        return $this->hasMany(DividendPayment::class);
    }

    public function userDividendRecords(): HasMany
    {
        return $this->hasMany(UserDividendRecord::class);
    }

    // Get latest dividend payment information
    public function latestDividend()
    {
        return $this->dividendPayments()->orderBy('ex_dividend_date', 'desc')->first();
    }

    // Get dividend payments for a specific date range
    public function dividendsInRange($startDate, $endDate)
    {
        return $this->dividendPayments()
            ->whereBetween('ex_dividend_date', [$startDate, $endDate])
            ->orderBy('ex_dividend_date', 'desc')
            ->get();
    }
}
