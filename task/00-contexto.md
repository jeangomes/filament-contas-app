# Contexto geral do refactor DDD

## Por quê

O sistema tem duas pessoas (D e J) que dividem gastos comuns e individuais,
vindos de duas origens: itens de fatura de cartão (`credit_card_bill_id`
preenchido, mês de competência = `due_date` da fatura) e transações avulsas
(`transaction_date` direto). Hoje a regra de negócio central — dividir gastos
comuns 50/50, aplicar reembolso de despesas individuais pagas pela pessoa
errada ("dívida cruzada") e apurar quem deve pagar a diferença para o outro —
mora em `app/Services/MonthlyBalanceCalculator.php`, implementada como
consultas SQL (`DB::table`) manipulando arrays associativos. Isso significa
que a regra financeira mais importante do app **nunca foi unit-testada**: só
dá pra validar rodando contra um banco populado.

Já existe um começo de camada de domínio em `app/Domain/Finance` (`Money`,
`ExpenseScope`, `Transaction`, `ValorMonetarioInvalido`) usada hoje só na
importação de CSV (`app/Infra/Csv/LeitorDeFaturaCsv.php`).

## Objetivo

Estender essa base para também expressar a regra de rateio/acerto como um
serviço de domínio puro (sem SQL, sem Eloquent), testável com fixtures
simples em memória — e plugar essa regra no fluxo real via um repositório
(port/adapter), sem mudar o schema do banco.

## Fora de escopo (não mexer nesta rodada)

- Cálculo de despesas da casa / custo de vida em `MonthlySummaryService`
  (métodos `calculateHomeExpenses`, `calculateCreditCardData`,
  `calculateLivingCost`).
- Páginas legadas `app/Filament/Pages/LivingCost.php` e
  `app/Filament/Pages/ComputedResults.php` (já têm código morto/comentado,
  ex.: `'D' => 78,//rever` em `LivingCost.php`).
- Mudanças de schema (a coluna `scope` adicionada na migration
  `2026_08_27_163708_add_fields_to_transactions_table.php` não é usada por
  este refactor; continuamos lendo `common_expense`/`individual_expense`).

Apenas o método `calculateBalances()` de `MonthlySummaryService` passa a
consumir o novo serviço de domínio, mantendo sua assinatura atual.

## Bug a corrigir de passagem

`Transaction::getResponsibleId(): string` e `isShared(): bool`
(`app/Domain/Finance/Entities/Transaction.php:26-27`) têm retorno não-nulo
mas as propriedades são `string|null`/`bool|null` — `TypeError` garantido se
chamados hoje, já que `LeitorDeFaturaCsv` sempre passa `null` nesses campos.
Além disso, `isShared` é um conceito redundante com `scope`/`ExpenseScope` e
nunca é preenchido em produção, deixando `isCrossDebt()` de fato inoperante.
A Task 02 remove `isShared` e baseia `isCrossDebt()` só em `scope` +
`responsible`.

## Ordem de execução

As tasks (`01` a `11`) são sequenciais — cada uma assume as anteriores
concluídas — e cada uma termina em um ponto onde o projeto continua
funcionando (sem quebrar o fluxo atual de importação de CSV nem o cálculo de
resumo mensal). Ver `task/01-*.md` até `task/11-*.md`.

Plano completo (histórico da sessão que gerou este refactor):
`/home/jeangomes/.claude/plans/crispy-meandering-heron.md`.
