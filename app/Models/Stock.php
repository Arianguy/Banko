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
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'day_change' => 'decimal:2',
        'day_change_percent' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function userTransactions($userId): HasMany
    {
        return $this->hasMany(StockTransaction::class)->where('user_id', $userId);
    }
}
