<?php

namespace App\Models;

use App\Domain\Finance\Enums\Participant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O fechamento do mês sob a ótica de um participante.
 *
 * `living_cost` responde "quanto a vida dele custou"; `balance` responde "ele
 * adiantou ou ficou devendo". São perguntas diferentes e não se somam.
 *
 * @property Participant $participant
 * @property string      $card_total
 * @property string      $card_common
 * @property string      $card_individual
 * @property string      $paid_common
 * @property string      $share_common
 * @property string      $cross_debt_adjustment
 * @property string      $balance
 * @property string      $living_cost
 */
class MonthlyParticipantSummary extends Model
{
    /**
     * `monthly_summary_id` fica de fora de propósito: estas linhas nascem
     * pela relação (`$summary->participantSummaries()->create(...)`), que
     * preenche a FK sozinha. Mesma convenção de `Transaction`.
     */
    protected $fillable = [
        'participant',
        'card_total',
        'card_common',
        'card_individual',
        'paid_common',
        'share_common',
        'cross_debt_adjustment',
        'balance',
        'living_cost',
    ];

    protected function casts(): array
    {
        return [
            'participant' => Participant::class,
            'card_total' => 'decimal:2',
            'card_common' => 'decimal:2',
            'card_individual' => 'decimal:2',
            'paid_common' => 'decimal:2',
            'share_common' => 'decimal:2',
            'cross_debt_adjustment' => 'decimal:2',
            'balance' => 'decimal:2',
            'living_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<MonthlySummary, $this> */
    public function monthlySummary(): BelongsTo
    {
        return $this->belongsTo(MonthlySummary::class);
    }
}
