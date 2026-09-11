<?php

namespace Tests\Feature;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Models\CreditCardBill;
use App\Models\MonthlyHomeExpense;
use App\Models\MonthlyParticipantSummary;
use App\Models\MonthlySummary;
use App\Models\Transaction;
use App\Services\FechamentoMensalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FechamentoMensalServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function persiste_o_mes_com_participantes_e_detalhe_da_casa(): void
    {
        // Casa: 300,00 (J) + 120,00 (D) = 420,00
        $this->avulsa(['who_paid' => 'J', 'amount' => '300.00', 'description' => 'Aluguel']);
        $this->avulsa(['who_paid' => 'D', 'amount' => '120.00', 'description' => 'LIGHT']);

        $resumo = $this->apurar('2026-01');

        $this->assertSame(1, MonthlySummary::query()->count());
        $this->assertSame('2026-01-01', $resumo->competencia->format('Y-m-d'));
        $this->assertSame('420.00', $resumo->total_home_expenses);
        $this->assertSame('420.00', $resumo->total_common_expenses);
        $this->assertNotNull($resumo->calculated_at);

        // Cota de 210,00 para cada: J pagou 300 (saldo +90), D pagou 120 (-90).
        $this->assertSame(2, $resumo->participantSummaries->count());
        $this->assertSame('90.00', $this->de($resumo, Participant::J)->balance);
        $this->assertSame('-90.00', $this->de($resumo, Participant::D)->balance);
        $this->assertSame('210.00', $this->de($resumo, Participant::D)->living_cost);

        $this->assertSame('90.00', $resumo->settlement_amount);
        $this->assertSame(Participant::D, $resumo->settlement_payer);

        $this->assertSame(
            ['Aluguel' => '300.00', 'LIGHT' => '120.00'],
            $resumo->homeExpenses->pluck('amount', 'label')->all(),
        );
    }

    #[Test]
    public function recalcular_nao_duplica_nada(): void
    {
        $this->avulsa(['who_paid' => 'J', 'amount' => '300.00', 'description' => 'Aluguel']);

        $this->apurar('2026-01');
        $segundo = $this->apurar('2026-01');

        $this->assertSame(1, MonthlySummary::query()->count());
        $this->assertSame(2, MonthlyParticipantSummary::query()->count());
        $this->assertSame(1, MonthlyHomeExpense::query()->count());
        $this->assertSame('300.00', $segundo->total_home_expenses);
    }

    #[Test]
    public function recalcular_reflete_mudanca_nos_lancamentos(): void
    {
        $lancamento = $this->avulsa(['who_paid' => 'J', 'amount' => '300.00', 'description' => 'Aluguel']);
        $this->apurar('2026-01');

        $lancamento->update(['amount' => '500.00']);
        $resumo = $this->apurar('2026-01');

        $this->assertSame('500.00', $resumo->total_home_expenses);
        $this->assertSame('250.00', $resumo->settlement_amount);
    }

    #[Test]
    public function informacao_do_usuario_sobrevive_ao_recalculo(): void
    {
        $this->avulsa(['who_paid' => 'J', 'amount' => '300.00', 'description' => 'Aluguel']);

        $resumo = $this->apurar('2026-01');
        $resumo->update(['difference_paid' => true, 'calculation_notes' => 'Acertado no pix']);

        $recalculado = $this->apurar('2026-01');

        $this->assertTrue($recalculado->difference_paid);
        $this->assertSame('Acertado no pix', $recalculado->calculation_notes);
    }

    #[Test]
    public function custo_de_vida_vai_para_o_responsavel_e_nao_para_o_dono_do_cartao(): void
    {
        $faturaJ = $this->fatura('J', '2026-01-10');
        // Casa 200,00 paga por D.
        $this->avulsa(['who_paid' => 'D', 'amount' => '200.00', 'description' => 'Aluguel']);
        // Comum de 100,00 no cartão de J.
        $this->itemDeFatura($faturaJ, ['who_paid' => 'J', 'amount' => '100.00']);
        // Individual de 60,00 no cartão de J, mas de responsabilidade de D.
        $this->itemDeFatura($faturaJ, [
            'who_paid' => 'J',
            'amount' => '60.00',
            'common_expense' => false,
            'individual_expense' => true,
            'responsible_for_expense' => 'D',
        ]);

        $resumo = $this->apurar('2026-01');

        // Comum total 300,00 -> cota de 150,00. O individual de 60 é de D.
        $this->assertSame('300.00', $resumo->total_common_expenses);
        $this->assertSame('200.00', $resumo->total_home_expenses);
        $this->assertSame('210.00', $this->de($resumo, Participant::D)->living_cost);
        $this->assertSame('150.00', $this->de($resumo, Participant::J)->living_cost);

        // O cartão continua sendo de J, mesmo com o custo indo para D.
        $this->assertSame('160.00', $this->de($resumo, Participant::J)->card_total);
        $this->assertSame('60.00', $this->de($resumo, Participant::J)->card_individual);
        $this->assertSame('0.00', $this->de($resumo, Participant::D)->card_total);
    }

    #[Test]
    public function mes_sem_lancamento_fecha_zerado_e_sem_devedor(): void
    {
        $resumo = $this->apurar('2026-01');

        $this->assertSame('0.00', $resumo->total_common_expenses);
        $this->assertSame('0.00', $resumo->settlement_amount);
        $this->assertNull($resumo->settlement_payer);
        $this->assertSame(2, $resumo->participantSummaries->count());
        $this->assertCount(0, $resumo->homeExpenses);
    }

    #[Test]
    public function pagamento_de_fatura_gravado_no_banco_nao_move_os_numeros(): void
    {
        // Migrado de MonthlyBalanceCalculatorTest (removido na Task 09): a
        // exclusão está coberta no domínio, mas só aqui ela é verificada
        // saindo da coluna `type` do banco.
        $this->avulsa(['who_paid' => 'J', 'amount' => '300.00', 'description' => 'Aluguel']);
        $this->avulsa(['who_paid' => 'D', 'amount' => '999.00', 'type' => 'pgto_de_fatura']);

        $resumo = $this->apurar('2026-01');

        $this->assertSame('300.00', $resumo->total_common_expenses);
        $this->assertSame('150.00', $resumo->settlement_amount);
        $this->assertSame(Participant::D, $resumo->settlement_payer);
    }

    #[Test]
    public function divida_cruzada_gravada_no_banco_vira_acerto(): void
    {
        // Migrado de MonthlyBalanceCalculatorTest: sem nenhuma despesa comum,
        // o acerto vem inteiro da individual de D que J adiantou.
        $this->avulsa([
            'who_paid' => 'J',
            'amount' => '60.00',
            'common_expense' => false,
            'individual_expense' => true,
            'responsible_for_expense' => 'D',
        ]);

        $resumo = $this->apurar('2026-01');

        $this->assertSame('60.00', $this->de($resumo, Participant::J)->cross_debt_adjustment);
        $this->assertSame('-60.00', $this->de($resumo, Participant::D)->cross_debt_adjustment);
        $this->assertSame('60.00', $this->de($resumo, Participant::J)->balance);
        $this->assertSame('60.00', $resumo->settlement_amount);
        $this->assertSame(Participant::D, $resumo->settlement_payer);
        // O custo da despesa individual é de quem é responsável.
        $this->assertSame('60.00', $this->de($resumo, Participant::D)->living_cost);
        $this->assertSame('0.00', $this->de($resumo, Participant::J)->living_cost);
    }

    private function apurar(string $competencia): MonthlySummary
    {
        return app(FechamentoMensalService::class)
            ->apurarEPersistir(ReferenceMonth::fromString($competencia));
    }

    private function de(MonthlySummary $resumo, Participant $participante): MonthlyParticipantSummary
    {
        return $resumo->participantSummaries->firstWhere('participant', $participante);
    }

    private function fatura(string $dono, string $vencimento): CreditCardBill
    {
        return CreditCardBill::query()->create([
            'title_description_owner' => "Fatura {$dono}",
            'owner_bill' => $dono,
            'amount' => '0.00',
            'due_date' => $vencimento,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function avulsa(array $atributos): Transaction
    {
        return Transaction::query()->create($this->atributos($atributos));
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function itemDeFatura(CreditCardBill $fatura, array $atributos): Transaction
    {
        return $fatura->transactions()->create($this->atributos($atributos));
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function atributos(array $atributos): array
    {
        return array_merge([
            'transaction_date' => '2026-01-15',
            'description' => 'Lançamento de teste',
            'amount' => '100.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
        ], $atributos);
    }
}
