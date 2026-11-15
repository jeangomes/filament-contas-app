<?php

namespace Tests\Domain\Finance\Entities;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\Parcela;
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

    #[Test]
    public function responsavel_efetivo_e_quem_foi_apontado_como_responsavel(): void
    {
        $lancamento = $this->lancamento(
            whoPaid: Participant::J,
            scope: ExpenseScope::Individual,
            responsible: Participant::D,
        );

        $this->assertSame(Participant::D, $lancamento->responsavelEfetivo());
    }

    #[Test]
    public function sem_responsavel_apontado_quem_pagou_e_quem_arca(): void
    {
        $lancamento = $this->lancamento(
            whoPaid: Participant::J,
            scope: ExpenseScope::Individual,
            responsible: null,
        );

        $this->assertSame(Participant::J, $lancamento->responsavelEfetivo());
    }

    #[Test]
    public function lancamento_e_avulso_por_padrao(): void
    {
        $this->assertFalse($this->lancamento()->ehDeFatura());
    }

    #[Test]
    public function identifica_item_de_fatura(): void
    {
        $this->assertTrue($this->lancamento(deFatura: true)->ehDeFatura());
    }

    #[Test]
    public function lancamento_sem_parcela_tem_parcela_nula(): void
    {
        $lancamento = $this->lancamento();

        $this->assertNull($lancamento->parcela());
        $this->assertFalse($lancamento->ehParcelada());
    }

    #[Test]
    public function descricao_da_serie_remove_o_sufixo_de_parcela(): void
    {
        $lancamento = $this->lancamento(
            description: 'Garmin Proparts Ecomm - Parcela 3/6',
            parcela: Parcela::de(3, 6),
        );

        $this->assertTrue($lancamento->ehParcelada());
        $this->assertSame('Garmin Proparts Ecomm - Parcela 3/6', $lancamento->description());
        $this->assertSame('Garmin Proparts Ecomm', $lancamento->descricaoDaSerie());
    }

    private function lancamento(
        Participant $whoPaid = Participant::D,
        ExpenseScope $scope = ExpenseScope::Comum,
        ?Participant $responsible = null,
        TransactionType $type = TransactionType::FixedExpense,
        bool $deFatura = false,
        string $description = 'Lançamento de teste',
        ?Parcela $parcela = null,
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable('2026-01-15'),
            description: $description,
            amount: Money::emCentavos(1000),
            whoPaid: $whoPaid,
            scope: $scope,
            responsible: $responsible,
            type: $type,
            deFatura: $deFatura,
            parcela: $parcela,
        );
    }
}
