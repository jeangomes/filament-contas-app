# Task 08 — Adapter Eloquent (Mapper + Repositório)

## Status
Concluída

## Desvios em relação ao previsto
- `competenciasDisponiveis()` usa `COALESCE(ccb.due_date, transactions.transaction_date)`
  (SQL padrão, funciona em MySQL e SQLite) e agrega os meses em PHP, em vez
  do `DATE_FORMAT`/`IF` do MySQL previsto no plano — que quebraria os testes
  em SQLite.
- `TransactionMapper` reaproveita `ConversorDeValorBrasileiro` (que já aceita
  o formato decimal "2825.51") em vez de duplicar a conversão para centavos.
- Durante esta task, as renomeações não commitadas da migration
  (`title_description_owner`→`card_name` etc.) foram revertidas por decisão
  do usuário: elas quebravam qualquer teste com banco, já que model, factory
  e services ainda usam os nomes antigos. O patch com o rename ficou salvo em
  `<scratchpad>/rename-colunas-credit-card-bills.patch` para ser refeito
  depois com uma migration ALTER dedicada.

## Objetivo
Implementar a porta `RepositorioDeLancamentos` (Task 07) usando Eloquent,
convertendo `App\Models\Transaction` em `App\Domain\Finance\Entities\Transaction`.

## Arquivos a criar

### `app/Infra/Eloquent/TransactionMapper.php`
```php
<?php

declare(strict_types=1);

namespace App\Infra\Eloquent;

use App\Domain\Finance\Entities\Transaction as TransactionDominio;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
use App\Models\Transaction as TransactionEloquent;
use DateTimeImmutable;

final class TransactionMapper
{
    public function paraDominio(TransactionEloquent $model): TransactionDominio
    {
        $competencia = $model->creditCardBill?->due_date ?? $model->transaction_date;

        return new TransactionDominio(
            date: DateTimeImmutable::createFromInterface($competencia),
            description: $model->description,
            amount: $this->paraMoney($model),
            whoPaid: Participant::from($model->who_paid),
            scope: $model->common_expense ? ExpenseScope::Comum : ExpenseScope::Individual,
            responsible: $model->responsible_for_expense !== null
                ? Participant::from($model->responsible_for_expense)
                : null,
            type: TransactionType::from($model->type),
        );
    }

    private function paraMoney(TransactionEloquent $model): Money
    {
        // getRawOriginal() evita o cast 'amount' => 'float' do model, que
        // pode perder precisão antes de virar centavos.
        $bruto = (string) $model->getRawOriginal('amount'); // ex.: "-2825.51"

        $negativo = str_starts_with($bruto, '-');
        [$inteiro, $decimal] = array_pad(explode('.', ltrim($bruto, '-')), 2, '00');
        $centavos = (int) ($inteiro . str_pad(substr($decimal, 0, 2), 2, '0'));

        return Money::emCentavos($negativo ? -$centavos : $centavos);
    }
}
```
Nota: se preferir não duplicar a lógica de parsing de string decimal com a de
`app/Infra/Csv/ConversorDeValorBrasileiro.php`, avaliar extrair um método
comum (ex.: `Money::deStringDecimal(string $valor, string $separador = '.')`)
— mas isso é opcional nesta task; duplicar as ~5 linhas é aceitável se a
extração comum não sair trivial.

### `app/Infra/Eloquent/RepositorioDeLancamentosEloquent.php`
```php
<?php

declare(strict_types=1);

namespace App\Infra\Eloquent;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Models\Transaction as TransactionEloquent;

final class RepositorioDeLancamentosEloquent implements RepositorioDeLancamentos
{
    public function __construct(
        private readonly TransactionMapper $mapper = new TransactionMapper(),
    ) {}

    public function paraCompetencia(ReferenceMonth $mes): array
    {
        return TransactionEloquent::query()
            ->with('creditCardBill')
            ->where(function ($query) use ($mes) {
                $query->where(function ($semFatura) use ($mes) {
                    $semFatura->whereNull('credit_card_bill_id')
                        ->whereYear('transaction_date', $mes->ano())
                        ->whereMonth('transaction_date', $mes->numeroDoMes());
                })->orWhereHas('creditCardBill', function ($comFatura) use ($mes) {
                    $comFatura->whereYear('due_date', $mes->ano())
                        ->whereMonth('due_date', $mes->numeroDoMes());
                });
            })
            ->get()
            ->map(fn (TransactionEloquent $m) => $this->mapper->paraDominio($m))
            ->all();
    }

    public function competenciasDisponiveis(): array
    {
        $chaves = TransactionEloquent::query()
            ->leftJoin('credit_card_bills as ccb', 'transactions.credit_card_bill_id', '=', 'ccb.id')
            ->selectRaw("DISTINCT IF(credit_card_bill_id IS NOT NULL, DATE_FORMAT(ccb.due_date, '%Y-%m'), DATE_FORMAT(transaction_date, '%Y-%m')) AS chave")
            ->pluck('chave');

        return $chaves->map(fn (string $chave) => ReferenceMonth::fromString($chave))->all();
    }
}
```
`competenciasDisponiveis()` mantém a mesma expressão `IF(...)`/`DATE_FORMAT`
raw que existe hoje em `MonthlyBalanceCalculator::getCommonExpensesPaidByParticipants()`
— não há necessidade de reescrever isso em Eloquent puro, já que é só para
descobrir a lista de meses (a lógica de negócio de fato fica em
`paraCompetencia()` + `CalculadoraDeSaldoMensal`).

## Registro do binding
Em `app/Providers/AppServiceProvider.php`, no método `register()`:
```php
$this->app->bind(
    \App\Domain\Finance\Repositories\RepositorioDeLancamentos::class,
    \App\Infra\Eloquent\RepositorioDeLancamentosEloquent::class,
);
```

## Testes
`tests/Infra/Eloquent/RepositorioDeLancamentosEloquentTest.php` (feature test,
`use Illuminate\Foundation\Testing\RefreshDatabase;`):
- Criar uma `CreditCardBill` com `due_date` em um mês e uma `Transaction`
  ligada a ela com `transaction_date` em outro mês → `paraCompetencia()` do
  mês do `due_date` retorna essa transação; do mês do `transaction_date`, não.
- Criar uma `Transaction` avulsa (`credit_card_bill_id = null`) com
  `transaction_date` em um mês → aparece só na competência desse mês.
- Gravar um valor decimal com centavos (ex.: `2825.51`) e conferir que
  `TransactionMapper` produz `Money::emCentavos(282551)` exatamente (sem
  passar pelo cast float do model).
- `competenciasDisponiveis()` retorna as chaves esperadas para um pequeno
  conjunto de dados de teste.

## Critério de conclusão
- `php artisan test --filter=RepositorioDeLancamentosEloquentTest` passa.
- Binding registrado e resolvível via `app(RepositorioDeLancamentos::class)`.
