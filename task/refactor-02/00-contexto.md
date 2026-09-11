# Refactor 2 — Fechamento mensal: balanço histórico + custo de vida

## Por quê

O refactor 1 (ver `task/00-contexto.md` até `task/11-*.md`) tirou a regra de
rateio/acerto do SQL e a colocou no domínio, com `CalculadoraDeSaldoMensal`
testada. Ficou de fora a outra metade do sistema: **o custo de vida de cada
participante** e a **persistência do histórico mensal**.

Esse lado está espalhado e furado hoje:

- `MonthlySummaryService` calcula custo de vida a partir das colunas agregadas
  `credit_card_bills.common_amount/individual_amount`, que só são atualizadas
  no evento `updated` de `Transaction` (`app/Models/Transaction.php`,
  `booted()`). A importação de CSV dispara `created` — **os totais da fatura
  não se atualizam na importação**, e o custo de vida sai errado.
- A fórmula ignora despesas individuais avulsas (fora do cartão) e ignora
  `responsible_for_expense`: um gasto individual de D lançado no cartão de J
  conta como custo de vida de J.
- "Despesa da casa" é definida por lista fixa de `description` ('Aluguel',
  'Condomínio', 'Eventualidades', 'LIGHT', 'Naturgy', 'Claro'): uma avulsa
  comum com outra descrição entra no rateio mas some do total da casa — os
  dois totais não fecham.
- `monthly_summaries` tem colunas fixas por participante (`d_*`, `j_*`) e por
  categoria da casa, e há um CRUD Filament que deixa **editar à mão campos
  calculados** (`app/Filament/Resources/MonthlySummaries/`).
- Quatro páginas em `app/Filament/Pages/` repetindo o mesmo conceito:
  `ComputedResults` e `LivingCost` recalculam em SQL cru o que
  `MonthlySummaryPage` já mostra persistido.
- O registro de referência `2026-06` na verdade descreve a competência
  `2026-07` (referência + 1 mês), o que confunde a leitura.

## Decisões alinhadas com o usuário

1. **Custo de vida** deriva das transações: metade de todas as despesas comuns
   do mês + todas as individuais de responsabilidade da pessoa.
2. **Persistência normalizada**: uma linha por mês × participante, no lugar das
   colunas `d_*`/`j_*`.
3. **Cache recalculável**: qualquer mês pode ser recalculado e sobrescrito.
4. **Duas páginas**: "Consolidado Mensal" (persistido) + "Faturas Mensais".
   `ComputedResults`, `LivingCost` e o CRUD de `MonthlySummaries` saem.
5. **Casa = despesa comum sem cartão** (sem lista de descrições).
6. **Detalhe da casa em linhas** (mês × label × valor), não em colunas fixas.
7. **Chave = competência** (o mês em que se paga); "mês de referência" vira
   valor derivado só para exibição.
8. **Totais da fatura**: os cálculos somam as transações; as colunas agregadas
   viram cache de exibição, mantido por um observer corrigido.

## Regras de negócio

Para um mês de competência (item de fatura = mês do `due_date`; avulso = mês da
`transaction_date`), considerando só lançamentos que `contaParaBalanco()`
(exclui `pgto_de_fatura`):

```
total_comum          = soma das despesas comuns do mês (casa + cartões + avulsas)
casa                 = despesas comuns SEM cartão        (detalhe: por description)
cartao_total(P)      = soma das transações de fatura de P
cartao_comum(P)      = idem, só as comuns
cartao_individual(P) = idem, só as individuais

custo_de_vida(P)     = total_comum / N + soma(individuais cujo responsável é P)

saldo(P)             = já implementado (CalculadoraDeSaldoMensal)
acerto               = quem está positivo recebe de quem está negativo
```

### Premissa importante: responsável efetivo

"Responsável" por uma despesa individual = `responsible_for_expense` quando
preenchido, **senão quem pagou**. Sem esse fallback nenhuma despesa individual
entraria em nenhum custo de vida, porque hoje todos os
`responsible_for_expense` do banco real são `NULL`.

### O que o custo de vida NÃO é

O custo de vida **não** desconta o acerto: ele responde "quanto a vida de cada
um custou", independente de quem adiantou o dinheiro. O acerto continua sendo
a resposta de "quem paga quanto a quem".

## Modelo de dados alvo

```
monthly_summaries
  id, competencia (date, unique)
  total_home_expenses, total_common_expenses
  settlement_amount, settlement_payer (nullable)
  difference_paid (bool)          -- marca o que já foi acertado
  calculated_at, calculation_notes
  timestamps

monthly_participant_summaries     -- uma linha por mês × participante
  id, monthly_summary_id, participant
  card_total, card_common, card_individual
  paid_common, share_common, cross_debt_adjustment, balance
  living_cost
  unique(monthly_summary_id, participant)

monthly_home_expenses             -- detalhe da casa, sem colunas fixas
  id, monthly_summary_id, label, amount
```

## Ordem de execução

Tasks `01` a `10` neste diretório, sequenciais. Cada uma termina num ponto onde
o projeto continua funcionando — com uma exceção declarada: entre a task 04
(novo schema) e a task 09 (limpeza) o código legado que lê as colunas antigas
fica temporariamente quebrado; as tasks 05 e 08 o substituem.

## Ponto de atenção nos dados reais

A fatura 241 tem `amount` = 359,00 mas suas transações somam 125,00 (a 242
fecha certo: 483,00). Com o cálculo derivado das transações, `card_total` vai
mostrar 125,00 para ela. É a fatura que está com o total informado errado —
conferir antes de estranhar o número na tela.
