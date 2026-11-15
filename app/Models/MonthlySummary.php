<?php

namespace App\Models;

use App\Domain\Finance\Enums\Participant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Fechamento de um mês de competência.
 *
 * É cache recalculável: tudo aqui é derivado das transações e pode ser
 * reconstruído com `monthly:calculate`. A única exceção é `difference_paid`,
 * que é informação do usuário e sobrevive ao recálculo.
 *
 * @property Carbon           $competencia
 * @property string           $total_home_expenses
 * @property string           $total_common_expenses
 * @property string           $settlement_amount
 * @property Participant|null $settlement_payer
 * @property bool             $difference_paid
 * @property Carbon|null      $calculated_at
 * @property-read $reference_month
 * @property-read $balance_description
 */
class MonthlySummary extends Model
{
    protected $fillable = [
        'competencia',
        'total_home_expenses',
        'total_common_expenses',
        'settlement_amount',
        'settlement_payer',
        'difference_paid',
        'calculated_at',
        'calculation_notes',
    ];

    protected function casts(): array
    {
        return [
            'competencia' => 'date:Y-m-d',
            // decimal: 2 devolve string — dinheiro nunca passa por float.
            'total_home_expenses' => 'decimal:2',
            'total_common_expenses' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'settlement_payer' => Participant::class,
            'difference_paid' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    /** @return HasMany<MonthlyParticipantSummary, $this> */
    public function participantSummaries(): HasMany
    {
        return $this->hasMany(MonthlyParticipantSummary::class);
    }

    /** @return HasMany<MonthlyHomeExpense, $this> */
    public function homeExpenses(): HasMany
    {
        return $this->hasMany(MonthlyHomeExpense::class);
    }

    public function participantSummary(Participant $participante): ?MonthlyParticipantSummary
    {
        return $this->participantSummaries
            ->firstWhere('participant', $participante);
    }

    /**
     * O mês em que os gastos aconteceram — um mês antes do vencimento que a
     * competência representa. Derivado, só para exibição.
     */
    protected function referenceMonth(): Attribute
    {
        return Attribute::make(
            get: fn (): Carbon => $this->competencia->copy()->subMonth(),
        );
    }

    protected function formattedSettlement(): Attribute
    {
        return Attribute::make(
            get: fn (): string => number_format((float) $this->settlement_amount, 2, ',', '.'),
        );
    }

    protected function balanceDescription(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->settlement_payer === null || (float) $this->settlement_amount === 0.0) {
                    return 'Contas em dia';
                }

                return "{$this->settlement_payer->value} deve pagar <br/> R$ $this->formatted_settlement";
            },
        );
    }
}
