<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * @property string $owner_bill
 */
class CreditCardBill extends Model
{
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->setDescriptionForEvent(fn(string $eventName) => $this->getDescriptionForLog($eventName))
            ->logOnlyDirty();
    }

    private function getDescriptionForLog($eventName): string
    {
        return match ($eventName) {
            'updated' => 'Alteração de Fatura',
            'created' => 'Inclusão de Fatura',
            'deleted' => 'Exclusão de Fatura',
            default => $eventName,
        };
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
