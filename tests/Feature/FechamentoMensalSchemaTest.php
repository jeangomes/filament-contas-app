<?php

namespace Tests\Feature;

use App\Domain\Finance\Enums\Participant;
use App\Models\MonthlyHomeExpense;
use App\Models\MonthlyParticipantSummary;
use App\Models\MonthlySummary;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garante que as restrições do schema são reais, e não só intenção no DDL:
 * cascata, unicidade e precisão do dinheiro na ida e volta ao banco.
 */
final class FechamentoMensalSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function relaciona_mes_participantes_e_despesas_da_casa(): void
    {
        $fechamento = $this->fechamento('2026-07-01');
        $fechamento->participantSummaries()->create(['participant' => Participant::D]);
        $fechamento->homeExpenses()->create(['label' => 'Aluguel', 'amount' => '1200.00']);

        $recarregado = MonthlySummary::query()
            ->with(['participantSummaries', 'homeExpenses'])
            ->find($fechamento->id);

        $this->assertCount(1, $recarregado->participantSummaries);
        $this->assertCount(1, $recarregado->homeExpenses);
        $this->assertSame(Participant::D, $recarregado->participantSummaries->first()->participant);
        $this->assertTrue($recarregado->homeExpenses->first()->monthlySummary->is($fechamento));
    }

    #[Test]
    public function apagar_o_mes_leva_as_linhas_filhas_junto(): void
    {
        $fechamento = $this->fechamento('2026-07-01');
        $fechamento->participantSummaries()->create(['participant' => Participant::D]);
        $fechamento->homeExpenses()->create(['label' => 'Aluguel', 'amount' => '1200.00']);

        $fechamento->delete();

        $this->assertSame(0, MonthlyParticipantSummary::query()->count());
        $this->assertSame(0, MonthlyHomeExpense::query()->count());
    }

    #[Test]
    public function nao_aceita_dois_fechamentos_para_a_mesma_competencia(): void
    {
        $this->fechamento('2026-07-01');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->fechamento('2026-07-01');
    }

    #[Test]
    public function nao_aceita_o_mesmo_participante_duas_vezes_no_mesmo_mes(): void
    {
        $fechamento = $this->fechamento('2026-07-01');
        $fechamento->participantSummaries()->create(['participant' => Participant::D]);

        $this->expectException(UniqueConstraintViolationException::class);

        $fechamento->participantSummaries()->create(['participant' => Participant::D]);
    }

    #[Test]
    public function o_mesmo_participante_pode_aparecer_em_meses_diferentes(): void
    {
        $this->fechamento('2026-07-01')->participantSummaries()->create(['participant' => Participant::D]);
        $this->fechamento('2026-08-01')->participantSummaries()->create(['participant' => Participant::D]);

        $this->assertSame(2, MonthlyParticipantSummary::query()->count());
    }

    #[Test]
    public function valores_monetarios_voltam_do_banco_sem_perder_centavo(): void
    {
        $fechamento = $this->fechamento('2026-07-01', ['total_common_expenses' => '2825.51']);
        $fechamento->participantSummaries()->create([
            'participant' => Participant::J,
            'balance' => '-1412.76', // saldo negativo precisa sobreviver
            'living_cost' => '1412.75',
        ]);

        $recarregado = MonthlySummary::query()->with('participantSummaries')->find($fechamento->id);
        $participante = $recarregado->participantSummaries->first();

        $this->assertSame('2825.51', $recarregado->total_common_expenses);
        $this->assertSame('-1412.76', $participante->balance);
        $this->assertSame('1412.75', $participante->living_cost);
    }

    #[Test]
    public function a_fk_nao_e_atribuivel_em_massa(): void
    {
        $fechamento = $this->fechamento('2026-07-01');

        // Fora do fillable de propósito: estas linhas nascem pela relação.
        $this->assertNotContains('monthly_summary_id', (new MonthlyParticipantSummary())->getFillable());
        $this->assertNotContains('monthly_summary_id', (new MonthlyHomeExpense())->getFillable());
        $this->assertTrue($fechamento->exists);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function fechamento(string $competencia, array $atributos = []): MonthlySummary
    {
        return MonthlySummary::query()->create(array_merge([
            'competencia' => $competencia,
        ], $atributos));
    }
}
