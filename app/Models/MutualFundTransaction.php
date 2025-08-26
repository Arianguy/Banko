<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutualFundTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'mutual_fund_id',
        'transaction_type',
        'units',
        'nav',
        'amount',
        'transaction_date',
        'folio_number',
        'stamp_duty',
        'transaction_charges',
        'gst',
        'net_amount',
        'order_id',
        'notes',
    ];

    protected $casts = [
        'units' => 'decimal:6',
        'nav' => 'decimal:4',
        'amount' => 'decimal:2',
        'stamp_duty' => 'decimal:2',
        'transaction_charges' => 'decimal:2',
        'gst' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mutualFund(): BelongsTo
    {
        return $this->belongsTo(MutualFund::class);
    }
}
