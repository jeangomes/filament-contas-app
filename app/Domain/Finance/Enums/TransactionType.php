<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum TransactionType: string
{
    case FixedExpense = 'fixed_expense';
    case VariableExpense = 'variable_expense';
    case Payment = 'payment';
    case Superfluous = 'superfluous';
    case BillPayment = 'pgto_de_fatura';

    public function contaParaBalanco(): bool
    {
        return $this !== self::BillPayment;
    }
}
