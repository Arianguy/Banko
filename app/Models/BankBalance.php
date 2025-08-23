<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankBalance extends Model
{
    protected $fillable = [
        'user_id',
        'bank_id',
        'account_number',
        'balance',
        'update_date',
    ];

    protected $casts = [
        'update_date' => 'date',
        'balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
