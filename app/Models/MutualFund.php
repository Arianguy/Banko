<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MutualFund extends Model
{
    protected $fillable = [
        'scheme_code',
        'scheme_name',
        'fund_house',
        'category',
        'sub_category',
        'current_nav',
        'nav_date',
        'expense_ratio',
        'fund_type',
        'is_active',
    ];

    protected $casts = [
        'current_nav' => 'decimal:4',
        'expense_ratio' => 'decimal:2',
        'nav_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(MutualFundTransaction::class);
    }

    public function userTransactions($userId): HasMany
    {
        return $this->hasMany(MutualFundTransaction::class)->where('user_id', $userId);
    }
}
