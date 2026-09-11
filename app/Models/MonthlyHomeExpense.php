<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha do detalhe das despesas da casa no mês.
 *
 * `label` é a descrição da transação, sem lista fixa de categorias: uma
 * despesa nova da casa não exige mudança de schema.
 *
 * @property string $label
 * @property string $amount
 */
class MonthlyHomeExpense extends Model
{
    /** FK fora do fillable: estas linhas nascem pela relação. */
    protected $fillable = [
        'label',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<MonthlySummary, $this> */
    public function monthlySummary(): BelongsTo
    {
        return $this->belongsTo(MonthlySummary::class);
    }
}
