<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

/*    public function setTransactionDateAttribute($value): void
    {
        $monthNames = [
            'JAN' => '01',
            'FEV' => '02',
            'MAR' => '03',
            'ABR' => '04',
            'MAI' => '05',
            'JUN' => '06',
            'JUL' => '07',
            'AGO' => '08',
            'SET' => '09',
            'OUT' => '10',
            'NOV' => '11',
            'DEZ' => '12',
        ];

        // Assume que o valor seja uma string como '04 ABR'
        preg_match('/(\d{2}) (\w{3})/', $value, $matches);
        $day = $matches[1];
        $month = $monthNames[strtoupper($matches[2])];
        $year = date('Y'); // Ano atual, ou pode ser um ano específico
        $this->attributes['transaction_date'] = "{$year}-{$month}-{$day}";
    }*/
}
