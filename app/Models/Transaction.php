<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read CreditCardBill $creditCardBill
 */
class Transaction extends Model
{
    protected $fillable = [
        'transaction_date',
        'description',
        'parcelas',
        'amount',
        'common_expense',
        'individual_expense',
        'owner_expense',
        'who_paid'
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'common_expense' => 'boolean',
            'individual_expense' => 'boolean',
            'transaction_date' => 'date',
        ];
    }

    public function creditCardBill(): BelongsTo
    {
        return $this->belongsTo(CreditCardBill::class);
    }

    protected static function booted(): void
    {
        static::updated(function (Transaction $transaction) {
            $transaction->load(['creditCardBill']);
            $creditCardBill = $transaction->creditCardBill;
            $creditCardBill->common_amount = $creditCardBill->transactions()
                ->where('common_expense', '=', 1)
                ->sum('amount');
            $creditCardBill->individual_amount = $creditCardBill->transactions()
                ->where('individual_expense', '=', 1)
                ->sum('amount');
            $creditCardBill->save();
        });
    }
}
