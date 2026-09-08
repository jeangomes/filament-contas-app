<?php

namespace Tests\Domain\Finance\Entities;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionTest extends TestCase
{
    #[Test]
    public function despesa_individual_paga_por_quem_nao_e_o_responsavel_e_divida_cruzada(): void
    {
        $lancamento = $this->lancamento(
            whoPaid: Participant::J,
            scope: ExpenseScope::Individual,
            responsible: Participant::D,
        );

        $this->assertTrue($lancamento->isCrossDebt());
    }

    #[Test]
    public function despesa_individual_paga_pelo_proprio_responsavel_nao_e_divida_cruzada(): void
    {
        $lancamento = $this->lancamento(
            whoPaid: Participant::D,
            scope: ExpenseScope::Individual,
            responsible: Participant::D,
        );

        $this->assertFalse($lancamento->isCrossDebt());
    }

    #[Test]
    public function despesa_comum_nunca_e_divida_cruzada_mesmo_com_responsavel_diferente(): void
    {
        $lancamento = $this->lancamento(
            whoPaid: Participant::J,
            scope: ExpenseScope::Comum,
            responsible: Participant::D,
        );

        $this->assertFalse($lancamento->isCrossDebt());
    }

    #[Test]
    public function despesa_individual_sem_responsavel_definido_nao_e_divida_cruzada(): void
    {
        $lancamento = $this->lancamento(
            whoPaid: Participant::J,
            scope: ExpenseScope::Individual,
            responsible: null,
        );

        $this->assertFalse($lancamento->isCrossDebt());
    }

    #[Test]
    public function apenas_pagamento_de_fatura_nao_conta_para_o_balanco(): void
    {
        $comum = $this->lancamento(type: TransactionType::FixedExpense);
        $fatura = $this->lancamento(type: TransactionType::BillPayment);

        $this->assertTrue($comum->contaParaBalanco());
        $this->assertFalse($fatura->contaParaBalanco());
    }

    #[Test]
    public function competencia_e_derivada_da_data_do_lancamento(): void
    {
        $lancamento = new Transaction(
            date: new DateTimeImmutable('2026-03-15'),
            description: 'Lançamento de teste',
            amount: Money::emCentavos(1000),
            whoPaid: Participant::D,
            scope: ExpenseScope::Comum,
        );

        $this->assertTrue($lancamento->referenceMonth()->equals(ReferenceMonth::fromString('2026-03')));
    }

    private function lancamento(
        Participant $whoPaid = Participant::D,
        ExpenseScope $scope = ExpenseScope::Comum,
        ?Participant $responsible = null,
        TransactionType $type = TransactionType::FixedExpense,
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable('2026-01-15'),
            description: 'Lançamento de teste',
            amount: Money::emCentavos(1000),
            whoPaid: $whoPaid,
            scope: $scope,
            responsible: $responsible,
            type: $type,
        );
    }
}
