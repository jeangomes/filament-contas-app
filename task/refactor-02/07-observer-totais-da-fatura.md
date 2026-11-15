# Task 07 — Corrigir os totais agregados da fatura

## Status
Concluída — 124 testes passando (7 novos).

> **Revertido em parte, depois.** `created` e `deleted` foram removidos do
> observer: hoje **só `updated` recalcula**. A decisão é não pagar um SUM por
> linha inserida na importação, aceitando que os totais fiquem em zero até
> alguém editar alguma transação da fatura. Nenhum cálculo depende disso — o
> fechamento soma as transações pelo domínio. `TotaisDaFaturaTest` foi
> reescrito para esse contrato. O que está abaixo descreve o estado da época.

### O bug, confirmado por teste
`importar_transacoes_atualiza_os_totais` reproduz o caminho da importação de
CSV (`createMany`) e **falhava antes da correção**. Agora `created`,
`updated` e `deleted` recalculam.

### Melhorias além da correção
- **Uma consulta agregada** com `CASE WHEN`, em vez de dois `SUM` separados.
- **Update pelo query builder** em vez de `$fatura->save()`: não carrega o
  model e não dispara os eventos da fatura. Isso corta uma consulta por
  recálculo e, mais importante, evita que cada transação alterada gere uma
  entrada de "Alteração de Fatura" no log de atividades (`LogsActivity`) —
  numa importação de 100 linhas seriam 100 entradas de ruído.
- `common_amount`/`individual_amount` ganharam cast `decimal:2`. **Sem cast,
  voltavam como int no SQLite e string no MySQL** — mesmo atributo, tipo
  diferente entre teste e produção, em valor monetário. Descoberto porque um
  `assertSame` falhou; virou correção em vez de ajuste de asserção.
- Mudança de fatura (`updated` com `credit_card_bill_id` diferente) recalcula
  as duas faturas, com teste.

### Desempenho: medido, não suposto
A task mandava medir antes de otimizar. Com o observer ligado:

| itens importados | tempo | queries/item |
|---|---|---|
| 50 | 0,124s | 4 |
| 100 | 0,248s | 4 |
| 300 | 0,758s | 4 |

Curva **linear** (~2,5ms/item), não quadrática: o custo é dominado pelo
round-trip, não pela varredura da tabela. Uma fatura realista (100-200 linhas)
importa em 0,25-0,5s, dentro de uma ação de formulário. **Nenhuma otimização
foi feita** — não há problema a resolver. Se um dia uma fatura passar de uns
milhares de itens, aí sim vale recalcular uma vez ao final da importação.

### Limitação conhecida (documentada no observer)
Escrita em massa por query builder (`Transaction::where(...)->delete()`) não
dispara eventos de model e não recalcula. Quem fizer isso precisa recalcular
na mão.

### Fora de escopo, mas anotado
`CreditCardBill::$casts` ainda tem `'amount' => 'float'`. É o total informado
pelo usuário, lido por outras telas — não mexi, mas é a última coluna
monetária do model que passa por float.

## Objetivo
Consertar um bug real: os totais `common_amount`/`individual_amount` de
`credit_card_bills` ficam desatualizados justamente no caminho mais usado, a
importação de CSV.

## O bug
`app/Models/Transaction.php`, `booted()`:

```php
static::updated(function (Transaction $transaction) { ... recalcula a fatura ... });
```

Só `updated`. A importação (`CreateCreditCardBill::afterCreate()` →
`$fatura->transactions()->createMany(...)`) dispara `created`, e apagar uma
transação dispara `deleted` — **nenhum dos dois recalcula nada**. Os totais só
se acertam se alguém editar uma transação depois, à mão.

Esses totais alimentam a página "Faturas Mensais"
(`resources/views/filament/pages/bills-side-by-side-overview.blade.php` lê
`amount`, `common_amount` e `individual_amount`) e, hoje, o custo de vida — que
depois deste refactor passa a sair do domínio (decisão 8).

## Correção

Mover a lógica para um observer dedicado
(`app/Observers/TransactionObserver.php`, registrado com o atributo
`#[ObservedBy]` no model ou em `AppServiceProvider::boot()`) reagindo a
`created`, `updated` e `deleted`, e recalculando:

```php
$fatura->common_amount = $fatura->transactions()
    ->where('common_expense', true)
    ->where('type', '!=', TransactionType::BillPayment->value)
    ->sum('amount');

$fatura->individual_amount = // idem, individual_expense
```

Pontos de atenção:
- Usar `TransactionType::BillPayment->value` em vez da string solta
  `'pgto_de_fatura'` — o enum existe desde o refactor 1.
- Em `updated`, se a transação **mudou de fatura**, recalcular as duas
  (`getOriginal('credit_card_bill_id')` e a atual).
- Em `deleted`, a relação já não resolve pelo model apagado — capturar o id
  antes.
- `createMany()` dispara um evento por linha: numa fatura de 100 itens são 100
  recálculos. Se pesar, avaliar recalcular uma vez ao final da importação em
  `CreateCreditCardBill::afterCreate()` em vez de por evento — mas só otimize
  se medir o problema.

**Não** sobrescrever `amount` (o total informado da fatura): ele é preenchido
pelo usuário no formulário e serve de conferência contra a soma das
transações. Nos dados reais a fatura 241 tem `amount` 359,00 com transações
somando 125,00 — a divergência é informação útil, não ruído a apagar.

## Testes (feature, `RefreshDatabase`)
1. Criar transações via `$fatura->transactions()->createMany([...])` atualiza
   `common_amount`/`individual_amount` — **este teste falha hoje**, é a prova
   do bug.
2. Apagar uma transação atualiza os totais.
3. Editar o valor de uma transação atualiza os totais (comportamento que já
   funcionava, para não regredir).
4. `pgto_de_fatura` não entra em nenhum dos dois totais.

## Critério de conclusão
- Os 4 testes verdes.
- `Transaction::booted()` sem lógica de negócio sobrando (ou removido, se o
  observer assumir tudo).
