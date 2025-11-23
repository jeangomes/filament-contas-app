<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * @property-read CreditCardBill $creditCardBill
 */
class Transaction extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'transaction_date',
        'description',
        'parcelas',
        'amount',
        'common_expense',
        'individual_expense',
        'responsible_for_expense','who_paid',
        'origin','type','category'
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->setDescriptionForEvent(fn(string $eventName) => $this->getDescriptionForLog($eventName))
            ->logOnlyDirty();
    }

    private function getDescriptionForLog($eventName): string
    {
        return match ($eventName) {
            'updated' => 'Alteração de Transação/Item de Fatura',
            'created' => 'Inclusão de Transação/Item de Fatura',
            'deleted' => 'Exclusão de Transação/Item de Fatura',
            default => $eventName,
        };
    }

    /** @return BelongsTo<CreditCardBill, $this> */
    public function creditCardBill(): BelongsTo
    {
        return $this->belongsTo(CreditCardBill::class);
    }

    protected static function booted(): void
    {
        static::updated(function (Transaction $transaction) {
            $transaction->load(['creditCardBill']);
            $creditCardBill = $transaction->creditCardBill;
            if ($creditCardBill) {
                $creditCardBill->common_amount = $creditCardBill->transactions()
                    ->where('common_expense', '=', 1)
                    ->where('type','!=', 'pgto_de_fatura')
                    ->sum('amount');
                $creditCardBill->individual_amount = $creditCardBill->transactions()
                    ->where('individual_expense', '=', 1)
                    ->where('type','!=', 'pgto_de_fatura')
                    ->sum('amount');
                $creditCardBill->save();
            }
        });
    }
}
