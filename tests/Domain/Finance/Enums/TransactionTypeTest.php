<?php

namespace Tests\Domain\Finance\Enums;

use App\Domain\Finance\Enums\TransactionType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionTypeTest extends TestCase
{
    #[Test]
    public function apenas_pagamento_de_fatura_nao_conta_para_o_balanco(): void
    {
        $this->assertFalse(TransactionType::BillPayment->contaParaBalanco());

        $this->assertTrue(TransactionType::FixedExpense->contaParaBalanco());
        $this->assertTrue(TransactionType::VariableExpense->contaParaBalanco());
        $this->assertTrue(TransactionType::Payment->contaParaBalanco());
        $this->assertTrue(TransactionType::Superfluous->contaParaBalanco());
    }
}
