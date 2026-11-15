<?php

namespace App\Models;

use App\Observers\TransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * @property int $credit_card_bill_id
 * @property-read CreditCardBill $creditCardBill
 * @property ?int $installment_current
 * @property ?int $installment_total
 * @property $transaction_date
 * @property string $description
 */
#[ObservedBy(TransactionObserver::class)]
class Transaction extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'transaction_date',
        'description',
        'amount',
        'common_expense',
        'individual_expense',
        'responsible_for_expense','who_paid',
        'origin','type','expense_category_id',
        'installment_current','installment_total',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'common_expense' => 'boolean',
            'individual_expense' => 'boolean',
            'transaction_date' => 'date',
            'installment_current' => 'integer',
            'installment_total' => 'integer',
        ];
    }

    /**
     * O rótulo da parcela ("3/6"), ou null quando a compra não é parcelada.
     */
    public function rotuloDaParcela(): ?string
    {
        if ($this->installment_current === null || $this->installment_total === null) {
            return null;
        }

        return "{$this->installment_current}/{$this->installment_total}";
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

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }
}
