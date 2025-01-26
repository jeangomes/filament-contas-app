<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardBill extends Model
{

    protected $fillable = [
        'title_description_owner',
        'reference_date',
        'due_date',
        'amount',
    ];
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
