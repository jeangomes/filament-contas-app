<?php

namespace Tests\Domain\Finance\Entities;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\Rubrica;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Services\ApuradorDeFechamentoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionRubricaTest extends TestCase
{
    #[Test]
    public function comum_de_fatura_e_comum_de_cartao(): void
    {
        $lancamento = $this->lancamento(10000, Participant::D, ExpenseScope::Comum, deFatura: true);

        $this->assertSame(Rubrica::ComumDeCartao, $lancamento->rubrica());
    }

    #[Test]
    public function comum_avulsa_e_despesa_da_casa(): void
    {
        $lancamento = $this->lancamento(30000, Participant::J, ExpenseScope::Comum);

        $this->assertSame(Rubrica::ComumDaCasa, $lancamento->rubrica());
    }

    #[Test]
    public function individual_sem_responsavel_e_de_quem_pagou(): void
    {
        $lancamento = $this->lancamento(4000, Participant::D, ExpenseScope::Individual, deFatura: true);

        $this->assertSame(Rubrica::IndividualPropria, $lancamento->rubrica());
    }

    #[Test]
    public function individual_com_responsavel_igual_a_quem_pagou_e_propria(): void
    {
        $lancamento = $this->lancamento(4000, Participant::D, ExpenseScope::Individual, responsible: Participant::D);

        $this->assertSame(Rubrica::IndividualPropria, $lancamento->rubrica());
    }

    #[Test]
    public function individual_paga_por_quem_nao_e_o_responsavel_e_divida_cruzada(): void
    {
        $lancamento = $this->lancamento(5000, Participant::D, ExpenseScope::Individual, responsible: Participant::J);

        $this->assertSame(Rubrica::DividaCruzada, $lancamento->rubrica());
    }

    #[Test]
    public function pagamento_de_fatura_fica_fora_do_rateio_mesmo_marcado_como_comum(): void
    {
        $lancamento = $this->lancamento(
            -50000,
            Participant::D,
            ExpenseScope::Comum,
            type: TransactionType::BillPayment,
            deFatura: true,
        );

        $this->assertSame(Rubrica::ForaDoRateio, $lancamento->rubrica());
    }

    #[Test]
    public function so_as_comuns_entram_no_rateio_e_so_o_pagamento_de_fatura_nao_e_gasto(): void
    {
        $this->assertTrue(Rubrica::ComumDeCartao->entraNoRateio());
        $this->assertTrue(Rubrica::ComumDaCasa->entraNoRateio());
        $this->assertFalse(Rubrica::IndividualPropria->entraNoRateio());
        $this->assertFalse(Rubrica::DividaCruzada->entraNoRateio());
        $this->assertFalse(Rubrica::ForaDoRateio->entraNoRateio());

        foreach (Rubrica::cases() as $rubrica) {
            $this->assertSame($rubrica !== Rubrica::ForaDoRateio, $rubrica->contaComoGasto());
            $this->assertNotSame('', $rubrica->rotulo());
            $this->assertNotSame('', $rubrica->explicacao());
        }
    }

    /**
     * As calculadoras não usam `rubrica()`: decidem pelos mesmos predicados,
     * cada uma do seu jeito. Este teste é o que impede as duas leituras de
     * divergirem — somar por rubrica tem que dar o que o apurador calcula.
     */
    #[Test]
    public function somar_por_rubrica_bate_com_o_que_o_apurador_calcula(): void
    {
        $lancamentos = [
            $this->lancamento(30001, Participant::J, ExpenseScope::Comum),                    // casa (centavo ímpar)
            $this->lancamento(12000, Participant::D, ExpenseScope::Comum),                    // casa
            $this->lancamento(10000, Participant::D, ExpenseScope::Comum, deFatura: true),
            $this->lancamento(-2500, Participant::D, ExpenseScope::Comum, deFatura: true),    // estorno
            $this->lancamento(8000, Participant::J, ExpenseScope::Comum, deFatura: true),
            $this->lancamento(4000, Participant::D, ExpenseScope::Individual, deFatura: true),
            $this->lancamento(1500, Participant::J, ExpenseScope::Individual),                // individual avulsa
            $this->lancamento(5000, Participant::J, ExpenseScope::Individual, responsible: Participant::D, deFatura: true),
            $this->lancamento(700, Participant::D, ExpenseScope::Individual, responsible: Participant::J),
            $this->lancamento(-60000, Participant::D, ExpenseScope::Comum, type: TransactionType::BillPayment, deFatura: true),
        ];

        $fechamento = (new ApuradorDeFechamentoMensal())->apurar(
            ReferenceMonth::fromString('2026-07'),
            $lancamentos,
            Participant::cases(),
        );

        $this->assertTrue(
            $this->somar($lancamentos, static fn (Transaction $l): bool => $l->rubrica()->entraNoRateio())
                ->equals($fechamento->totalComum()),
        );
        $this->assertTrue(
            $this->somar($lancamentos, static fn (Transaction $l): bool => $l->rubrica() === Rubrica::ComumDaCasa)
                ->equals($fechamento->totalCasa()),
        );

        $totalGasto = $this->somar($lancamentos, static fn (Transaction $l): bool => $l->rubrica()->contaComoGasto());
        $somaDosCustos = Money::zero();

        foreach (Participant::cases() as $participante) {
            $doParticipante = $fechamento->de($participante);

            $adiantou = $this->somar($lancamentos, static fn (Transaction $l): bool => $l->rubrica() === Rubrica::DividaCruzada
                && $l->whoPaid() === $participante);
            $foiAdiantado = $this->somar($lancamentos, static fn (Transaction $l): bool => $l->rubrica() === Rubrica::DividaCruzada
                && $l->responsavelEfetivo() === $participante);

            $this->assertTrue(
                $adiantou->subtrair($foiAdiantado)->equals($doParticipante->ajusteDividaCruzada),
                "ajuste de dívida cruzada de {$participante->value}",
            );

            $individuais = $this->somar($lancamentos, static fn (Transaction $l): bool => $l->rubrica()->contaComoGasto()
                && ! $l->rubrica()->entraNoRateio()
                && $l->responsavelEfetivo() === $participante);

            $this->assertTrue(
                $doParticipante->cotaComum->somar($individuais)->equals($doParticipante->custoDeVida),
                "custo de vida de {$participante->value}",
            );

            $somaDosCustos = $somaDosCustos->somar($doParticipante->custoDeVida);
        }

        $this->assertTrue($somaDosCustos->equals($totalGasto));
    }

    /**
     * @param  list<Transaction>  $lancamentos
     * @param  callable(Transaction): bool  $filtro
     */
    private function somar(array $lancamentos, callable $filtro): Money
    {
        $total = Money::zero();

        foreach ($lancamentos as $lancamento) {
            if ($filtro($lancamento)) {
                $total = $total->somar($lancamento->amount());
            }
        }

        return $total;
    }

    private function lancamento(
        int $centavos,
        Participant $whoPaid,
        ExpenseScope $scope,
        ?Participant $responsible = null,
        TransactionType $type = TransactionType::FixedExpense,
        bool $deFatura = false,
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable('2026-07-10'),
            description: 'Lançamento',
            amount: Money::emCentavos($centavos),
            whoPaid: $whoPaid,
            scope: $scope,
            responsible: $responsible,
            type: $type,
            deFatura: $deFatura,
        );
    }
}
