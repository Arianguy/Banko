<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FixedDeposit extends Model
{
    protected $fillable = [
        'user_id',
        'bank',
        'accountno',
        'principal_amt',
        'maturity_amt',
        'start_date',
        'maturity_date',
        'term',
        'int_rate',
        'Int_amt',
        'Int_year',
        'matured',
        'closed',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'matured' => 'boolean',
        'closed' => 'boolean',
        'start_date' => 'date',
        'maturity_date' => 'date',
    ];
}
