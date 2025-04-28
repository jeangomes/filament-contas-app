<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $owner_bill
 */
class CreditCardBill extends Model
{
    protected $fillable = [
        'title_description_owner',
        'owner_bill',
        'observation',
        'due_date',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'due_date' => 'date:Y-m-d',
        ];
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
