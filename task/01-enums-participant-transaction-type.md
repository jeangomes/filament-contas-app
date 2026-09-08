# Task 01 — Enums Participant e TransactionType

## Status
Concluída

## Objetivo
Criar dois enums de domínio que hoje não existem, para eliminar strings soltas
(`'D'`, `'J'`) e o filtro mágico `type != 'pgto_de_fatura'` espalhado pelo
código legado.

## Arquivos a criar

### `app/Domain/Finance/Enums/Participant.php`
```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum Participant: string
{
    case D = 'D';
    case J = 'J';
}
```
Substitui os arrays soltos `['D','J']` /
`MonthlyBalanceCalculator::COMMON_EXPENSE_PARTICIPANTS` e as strings cruas
espalhadas pelo código por um tipo seguro. `Participant::cases()` dá a lista
dos dois participantes.

### `app/Domain/Finance/Enums/TransactionType.php`
```php
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
```
Espelha o enum `type` da coluna `transactions.type` (ver migration
`2025_01_26_211426_create_transactions_table.php:50`). `contaParaBalanco()`
substitui o `where('type', '!=', 'pgto_de_fatura')` que hoje aparece em duas
queries de `app/Services/MonthlyBalanceCalculator.php`.

## Testes
`tests/Domain/Finance/Enums/TransactionTypeTest.php` — um teste simples
confirmando que só `BillPayment` retorna `contaParaBalanco() === false` e
todos os outros `true`.

## Critério de conclusão
- `php artisan test --filter=TransactionTypeTest` passa.
- Nenhum outro arquivo é alterado nesta task (os dois enums ainda não são
  usados em lugar nenhum — isso acontece na Task 02 em diante).
