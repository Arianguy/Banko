<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    protected $fillable = ['name'];

    /**
     * Get the accounts for the bank.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}

