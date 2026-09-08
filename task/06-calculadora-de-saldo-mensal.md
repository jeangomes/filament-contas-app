# Task 06 — Serviço de domínio CalculadoraDeSaldoMensal

## Status
Concluída

## Objetivo
Este é **o principal entregável** do refactor: a regra de negócio de rateio +
dívida cruzada + acerto final, extraída de
`app/Services/MonthlyBalanceCalculator.php` (hoje SQL + arrays) para um
serviço de domínio puro, sem SQL, sem Eloquent, testável com uma lista de
`Transaction` montada à mão.

## Arquivo a criar

`app/Domain/Finance/Services/CalculadoraDeSaldoMensal.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\MonthlyBalance;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

final class CalculadoraDeSaldoMensal
{
    /**
     * @param list<Transaction> $lancamentos Lançamentos de qualquer período — o filtro por mês é feito aqui dentro.
     * @param list<Participant> $participantes Os participantes que dividem as despesas comuns (hoje sempre os 2 cases de Participant).
     */
    public function calcular(ReferenceMonth $mes, array $lancamentos, array $participantes): MonthlyBalance
    {
        $relevantes = array_values(array_filter(
            $lancamentos,
            static fn (Transaction $t) => $t->contaParaBalanco() && $t->referenceMonth()->equals($mes),
        ));

        $saldos = [];
        foreach ($participantes as $participante) {
            $saldos[$participante->value] = Money::zero();
        }

        // 1) Rateio das despesas comuns
        $totalComum = Money::zero();
        $pagoComumPorParticipante = $saldos; // mesma forma, valores zerados

        foreach ($relevantes as $lancamento) {
            if ($lancamento->ehDespesaComum()) {
                $chave = $lancamento->whoPaid()->value;
                $totalComum = $totalComum->somar($lancamento->amount());
                $pagoComumPorParticipante[$chave] = ($pagoComumPorParticipante[$chave] ?? Money::zero())
                    ->somar($lancamento->amount());
            }
        }

        $cotas = $totalComum->dividirIgualmente(count($participantes));
        foreach ($participantes as $indice => $participante) {
            $saldos[$participante->value] = $pagoComumPorParticipante[$participante->value]
                ->subtrair($cotas[$indice]);
        }

        // 2) Dívida cruzada: despesas individuais pagas por quem não é o responsável
        foreach ($relevantes as $lancamento) {
            if ($lancamento->isCrossDebt()) {
                $pagou = $lancamento->whoPaid()->value;
                $responsavel = $lancamento->responsible()->value; // isCrossDebt() garante não-nulo

                $saldos[$pagou] = $saldos[$pagou]->somar($lancamento->amount());
                $saldos[$responsavel] = $saldos[$responsavel]->subtrair($lancamento->amount());
            }
        }

        return new MonthlyBalance($mes, $saldos);
    }
}
```

## Testes

`tests/Domain/Finance/Services/CalculadoraDeSaldoMensalTest.php` — construir
`Transaction`s diretamente (sem repositório/Eloquent), seguindo o padrão de
`tests/Domain/Finance/ValueObjects/ValorTest.php` (`final class`, `#[Test]`,
nomes em português). Um helper privado tipo `private function lancamento(...):
Transaction` para reduzir boilerplate é bem-vindo.

Cenários obrigatórios:
1. **Pagamento igual**: D paga R$ 100 comum, J paga R$ 100 comum, mesmo mês →
   saldo de ambos é zero, `acertos()` vazio.
2. **Um paga tudo**: D paga R$ 200 comum, J paga R$ 0 → saldo D = +100,
   saldo J = -100 → `acertos()` = `[Acerto(recebe: D, paga: J, valor: 100)]`.
   Este é o teste que prova a frase do usuário: "o maior pagador recebe a
   diferença do menor pagador".
3. **Centavo ímpar**: total comum = R$ 100,01 dividido por 2 participantes →
   nenhum centavo se perde (usa `Money::dividirIgualmente()` da Task 04).
4. **Dívida cruzada**: despesa individual de R$ 50, `whoPaid = J`,
   `responsible = D`, `scope = Individual` → além do rateio comum, saldo de
   J soma +50 e saldo de D soma -50.
5. **Dívida cruzada não se aplica ao próprio responsável**: despesa
   individual com `whoPaid == responsible` → nenhum ajuste extra.
6. **Pagamento de fatura ignorado**: lançamento com
   `type = TransactionType::BillPayment` e valor alto → não entra em nenhum
   somatório.
7. **Mês errado ignorado**: lançamento de um `ReferenceMonth` diferente do
   pedido → não entra no cálculo.
8. Cenário combinado: comum + individual cruzada no mesmo mês, conferindo o
   saldo final de ambos participantes.

## Critério de conclusão
- `php artisan test --filter=CalculadoraDeSaldoMensalTest` passa com todos os
  cenários acima.
- Nenhuma classe deste teste importa `Illuminate\*`, `DB` ou toca banco —
  é 100% memória.
