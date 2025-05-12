<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedDeposit extends Model
{
    protected $fillable = [
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
