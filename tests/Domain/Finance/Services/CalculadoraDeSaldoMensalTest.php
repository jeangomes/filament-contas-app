<?php

namespace Tests\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Services\CalculadoraDeSaldoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CalculadoraDeSaldoMensalTest extends TestCase
{
    private CalculadoraDeSaldoMensal $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = new CalculadoraDeSaldoMensal();
    }

    #[Test]
    public function quando_os_dois_pagam_o_mesmo_valor_ninguem_deve_nada(): void
    {
        $saldo = $this->calcular([
            $this->comum(10000, Participant::D),
            $this->comum(10000, Participant::J),
        ]);

        $this->assertTrue($saldo->saldoDe(Participant::D)->ehZero());
        $this->assertTrue($saldo->saldoDe(Participant::J)->ehZero());
        $this->assertSame([], $saldo->acertos());
    }

    #[Test]
    public function quem_paga_tudo_recebe_metade_do_outro(): void
    {
        $saldo = $this->calcular([
            $this->comum(20000, Participant::D),
        ]);

        $this->assertTrue($saldo->saldoDe(Participant::D)->equals(Money::emCentavos(10000)));
        $this->assertTrue($saldo->saldoDe(Participant::J)->equals(Money::emCentavos(-10000)));

        $acertos = $saldo->acertos();
        $this->assertCount(1, $acertos);
        $this->assertSame(Participant::D, $acertos[0]->recebe);
        $this->assertSame(Participant::J, $acertos[0]->paga);
        $this->assertTrue($acertos[0]->valor->equals(Money::emCentavos(10000)));
    }

    #[Test]
    public function divisao_com_centavo_impar_nao_perde_dinheiro(): void
    {
        $saldo = $this->calcular([
            $this->comum(10001, Participant::D),
        ]);

        // Cota de D = 5001, cota de J = 5000 (o centavo extra fica na primeira cota).
        $this->assertTrue($saldo->saldoDe(Participant::D)->equals(Money::emCentavos(5000)));
        $this->assertTrue($saldo->saldoDe(Participant::J)->equals(Money::emCentavos(-5000)));

        $soma = $saldo->saldoDe(Participant::D)->somar($saldo->saldoDe(Participant::J));
        $this->assertTrue($soma->ehZero());
    }

    #[Test]
    public function despesa_individual_paga_pela_pessoa_errada_vira_divida_cruzada(): void
    {
        $saldo = $this->calcular([
            $this->individual(5000, whoPaid: Participant::J, responsible: Participant::D),
        ]);

        $this->assertTrue($saldo->saldoDe(Participant::J)->equals(Money::emCentavos(5000)));
        $this->assertTrue($saldo->saldoDe(Participant::D)->equals(Money::emCentavos(-5000)));

        $acertos = $saldo->acertos();
        $this->assertCount(1, $acertos);
        $this->assertSame(Participant::J, $acertos[0]->recebe);
        $this->assertSame(Participant::D, $acertos[0]->paga);
    }

    #[Test]
    public function despesa_individual_paga_pelo_proprio_responsavel_nao_altera_saldo(): void
    {
        $saldo = $this->calcular([
            $this->individual(5000, whoPaid: Participant::D, responsible: Participant::D),
        ]);

        $this->assertTrue($saldo->saldoDe(Participant::D)->ehZero());
        $this->assertTrue($saldo->saldoDe(Participant::J)->ehZero());
        $this->assertSame([], $saldo->acertos());
    }

    #[Test]
    public function pagamento_de_fatura_e_ignorado(): void
    {
        $saldo = $this->calcular([
            $this->comum(20000, Participant::D),
            $this->comum(999999, Participant::J, type: TransactionType::BillPayment),
        ]);

        // Só a despesa de 200,00 entra no rateio.
        $this->assertTrue($saldo->saldoDe(Participant::D)->equals(Money::emCentavos(10000)));
        $this->assertTrue($saldo->saldoDe(Participant::J)->equals(Money::emCentavos(-10000)));
    }

    #[Test]
    public function lancamento_de_outra_competencia_e_ignorado(): void
    {
        $saldo = $this->calcular([
            $this->comum(20000, Participant::D),
            $this->comum(50000, Participant::J, data: '2026-02-10'),
        ]);

        $this->assertTrue($saldo->saldoDe(Participant::D)->equals(Money::emCentavos(10000)));
        $this->assertTrue($saldo->saldoDe(Participant::J)->equals(Money::emCentavos(-10000)));
    }

    #[Test]
    public function combina_rateio_comum_com_divida_cruzada(): void
    {
        $saldo = $this->calcular([
            // Comuns: total 300,00 -> cota de 150,00 para cada um.
            $this->comum(20000, Participant::D),
            $this->comum(10000, Participant::J),
            // Individual de 40,00 de D, mas paga por J.
            $this->individual(4000, whoPaid: Participant::J, responsible: Participant::D),
        ]);

        // D: pagou 200,00 - cota 150,00 = +50,00; menos 40,00 da dívida cruzada = +10,00
        $this->assertTrue($saldo->saldoDe(Participant::D)->equals(Money::emCentavos(1000)));
        // J: pagou 100,00 - cota 150,00 = -50,00; mais 40,00 da dívida cruzada = -10,00
        $this->assertTrue($saldo->saldoDe(Participant::J)->equals(Money::emCentavos(-1000)));

        $acertos = $saldo->acertos();
        $this->assertCount(1, $acertos);
        $this->assertSame(Participant::D, $acertos[0]->recebe);
        $this->assertSame(Participant::J, $acertos[0]->paga);
        $this->assertTrue($acertos[0]->valor->equals(Money::emCentavos(1000)));
    }

    /**
     * @param  list<Transaction>  $lancamentos
     */
    private function calcular(array $lancamentos): \App\Domain\Finance\ValueObjects\MonthlyBalance
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
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable($data),
            description: 'Despesa comum',
            amount: Money::emCentavos($centavos),
            whoPaid: $whoPaid,
            scope: ExpenseScope::Comum,
            type: $type,
        );
    }

    private function individual(
        int $centavos,
        Participant $whoPaid,
        ?Participant $responsible,
        string $data = '2026-01-15',
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable($data),
            description: 'Despesa individual',
            amount: Money::emCentavos($centavos),
            whoPaid: $whoPaid,
            scope: ExpenseScope::Individual,
            responsible: $responsible,
        );
    }
}
