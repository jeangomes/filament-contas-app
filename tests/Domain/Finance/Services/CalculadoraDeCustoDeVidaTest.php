<?php

namespace Tests\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Services\CalculadoraDeCustoDeVida;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CalculadoraDeCustoDeVidaTest extends TestCase
{
    private CalculadoraDeCustoDeVida $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = new CalculadoraDeCustoDeVida();
    }

    #[Test]
    public function despesa_comum_custa_o_mesmo_para_os_dois_independente_de_quem_pagou(): void
    {
        // D adiantou tudo, mas a vida dos dois custou metade cada.
        $custos = $this->calcular([
            $this->comum(20000, whoPaid: Participant::D),
        ]);

        $this->assertTrue($custos['D']->equals(Money::emCentavos(10000)));
        $this->assertTrue($custos['J']->equals(Money::emCentavos(10000)));
    }

    #[Test]
    public function individual_no_proprio_cartao_entra_no_custo_de_quem_gastou(): void
    {
        $custos = $this->calcular([
            $this->individual(5000, whoPaid: Participant::D, responsible: Participant::D, deFatura: true),
        ]);

        $this->assertTrue($custos['D']->equals(Money::emCentavos(5000)));
        $this->assertTrue($custos['J']->ehZero());
    }

    #[Test]
    public function individual_no_cartao_do_outro_pesa_para_o_responsavel(): void
    {
        // Gasto individual de D lançado no cartão de J: encarece a vida de D.
        $custos = $this->calcular([
            $this->individual(5000, whoPaid: Participant::J, responsible: Participant::D, deFatura: true),
        ]);

        $this->assertTrue($custos['D']->equals(Money::emCentavos(5000)));
        $this->assertTrue($custos['J']->ehZero());
    }

    #[Test]
    public function individual_avulsa_fora_do_cartao_entra_no_custo_de_vida(): void
    {
        $custos = $this->calcular([
            $this->individual(3000, whoPaid: Participant::J, responsible: Participant::J, deFatura: false),
        ]);

        $this->assertTrue($custos['J']->equals(Money::emCentavos(3000)));
        $this->assertTrue($custos['D']->ehZero());
    }

    #[Test]
    public function individual_sem_responsavel_apontado_fica_com_quem_pagou(): void
    {
        $custos = $this->calcular([
            $this->individual(4000, whoPaid: Participant::J, responsible: null),
        ]);

        $this->assertTrue($custos['J']->equals(Money::emCentavos(4000)));
        $this->assertTrue($custos['D']->ehZero());
    }

    #[Test]
    public function pagamento_de_fatura_e_ignorado(): void
    {
        $custos = $this->calcular([
            $this->comum(20000, whoPaid: Participant::D),
            $this->comum(999999, whoPaid: Participant::J, type: TransactionType::BillPayment),
        ]);

        $this->assertTrue($custos['D']->equals(Money::emCentavos(10000)));
        $this->assertTrue($custos['J']->equals(Money::emCentavos(10000)));
    }

    #[Test]
    public function lancamento_de_outra_competencia_e_ignorado(): void
    {
        $custos = $this->calcular([
            $this->comum(20000, whoPaid: Participant::D),
            $this->comum(50000, whoPaid: Participant::J, data: '2026-02-10'),
            $this->individual(7000, whoPaid: Participant::D, responsible: Participant::D, data: '2026-02-10'),
        ]);

        $this->assertTrue($custos['D']->equals(Money::emCentavos(10000)));
        $this->assertTrue($custos['J']->equals(Money::emCentavos(10000)));
    }

    #[Test]
    public function divisao_do_comum_com_centavo_impar_nao_perde_dinheiro(): void
    {
        $custos = $this->calcular([
            $this->comum(10001, whoPaid: Participant::D),
            $this->individual(2500, whoPaid: Participant::J, responsible: Participant::J),
        ]);

        // O que sobra depois de tirar as individuais tem que fechar com o comum.
        $somaDosCustos = $custos['D']->somar($custos['J']);
        $this->assertTrue($somaDosCustos->subtrair(Money::emCentavos(2500))->equals(Money::emCentavos(10001)));
    }

    /**
     * @param  list<Transaction>  $lancamentos
     * @return array<string, Money>
     */
    private function calcular(array $lancamentos): array
    {
        return $this->calculadora->calcular(
            ReferenceMonth::fromString('2026-01'),
            $lancamentos,
            Participant::cases(),
        );
    }

    private function comum(
        int $centavos,
        Participant $whoPaid,
        TransactionType $type = TransactionType::FixedExpense,
        string $data = '2026-01-15',
        bool $deFatura = false,
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable($data),
            description: 'Despesa comum',
            amount: Money::emCentavos($centavos),
            whoPaid: $whoPaid,
            scope: ExpenseScope::Comum,
            type: $type,
            deFatura: $deFatura,
        );
    }

    private function individual(
        int $centavos,
        Participant $whoPaid,
        ?Participant $responsible,
        string $data = '2026-01-15',
        bool $deFatura = false,
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable($data),
            description: 'Despesa individual',
            amount: Money::emCentavos($centavos),
            whoPaid: $whoPaid,
            scope: ExpenseScope::Individual,
            responsible: $responsible,
            deFatura: $deFatura,
        );
    }
}
