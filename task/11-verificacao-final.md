# Task 11 — Verificação final

## Status
Concluída — 74 testes passando; comportamento comparado com o código legado
contra o banco real.

## Como a comparação antes/depois foi feita
Em vez de alternar o working tree, a versão legada de `MonthlyBalanceCalculator`
foi extraída de `git HEAD`, renomeada para um namespace `Legacy\` e executada
no mesmo processo que a nova, contra o mesmo banco, com um script temporário
(já removido). Os casos que os dados reais não cobriam (dívida cruzada,
`pgto_de_fatura`, total com centavo ímpar) foram testados numa **cópia** do
banco (`laravel_db_verif`, já removida), sem tocar nos dados reais.

## Resultado: 2 divergências, ambas intencionais
| mês / participante | legado | novo | veredito |
|---|---|---|---|
| 2026-07 D e J | idêntico | idêntico | OK |
| 2026-08 D (cota) | 50,01 | 50,01 | OK |
| 2026-08 D (saldo) | 50,01 | 50,00 | correção do centavo |
| 2026-08 J (cota/saldo) | 50,01 / -50,01 | 50,00 / -50,00 | correção do centavo |

Total comum de R$ 100,01 dividido por 2: o legado usava float (cota de
50,005 para cada, exibida/gravada como 50,01 nos dois lados — meio centavo
que não existe em dinheiro). O novo distribui 50,01 + 50,00, somando exato.
É a correção prevista na Task 04.

## Comportamento legado que precisou ser reproduzido
A comparação revelou que o cálculo antigo somava o valor da dívida cruzada ao
campo `total_paid_common` (linha 156 do original, comentada como "pode ser
útil para relatórios"), para a linha da página `ComputedResults` fechar:
"pagou X, deveria pagar Y, saldo Z". Sem isso a tela exibiria 1464 - 767 ≠ 787.
Solução: o domínio mantém os conceitos separados (`totalPagoComumDe()` e
`ajusteDeDividaCruzadaDe()`) e a soma acontece só na montagem do array legado,
em `MonthlyBalanceCalculator`. Coberto por teste
(`o_relatorio_soma_o_adiantamento_ao_total_pago_para_a_linha_exibida_fechar`).

## Verificações executadas
- `php artisan test`: 74 passando, 169 asserções.
- `php artisan monthly:calculate 2026-06` contra MySQL real: "D deve pagar
  R$ 697,00", batendo com o cálculo manual e com a comparação.
  **Atenção:** isso gravou uma linha em `monthly_summaries` (a tabela estava
  vazia). É o comportamento normal do comando; apagar se não for desejada.
- Lógica de `ComputedResults::mount()` executada contra o banco real: roda sem
  erro e todas as 5 chaves consumidas pela blade existem.
- Sem `dd()`/`dump()` nos arquivos alterados; sem resquício de `isShared` /
  `getResponsibleId`; sem SQL cru em `MonthlyBalanceCalculator`.

## Pendência conhecida (fora do escopo deste refactor)
`MonthlySummaryService::calculateHomeExpenses()` (linha 129) e
`calculateCreditCardData()` (linha 154) ainda usam `DATE_FORMAT`, função
exclusiva do MySQL — o que impede testar o resumo mensal ponta a ponta em
SQLite. Trocar por `whereYear`/`whereMonth` é a próxima etapa natural.

## Objetivo
Confirmar que o refactor não alterou o comportamento observável do sistema —
só moveu a regra de negócio de SQL/arrays para o domínio, tornando-a
unit-testável.

## Passos

1. **Suíte automatizada completa**
   ```
   php artisan test
   ```
   Deve passar 100%, incluindo:
   - Todos os testes novos de domínio (`tests/Domain/Finance/**`).
   - `tests/Infra/Csv/LeitorDeFaturaCsvTest.php` e
     `ConversorDeValorBrasileiroTest.php` (sem regressão).
   - `tests/Infra/Eloquent/RepositorioDeLancamentosEloquentTest.php` (Task 08).
   - Qualquer teste de feature pré-existente relacionado a
     `MonthlySummaryService`/`CreateCreditCardBill`.

2. **Comparação antes/depois com dados reais**
   Em um ambiente local com dados de exemplo (ex.: `php artisan db:seed` ou um
   dump de desenvolvimento):
   - Antes de aplicar as Tasks 06/10 (ou usando um checkout do código antes
     do refactor, se disponível), rodar:
     ```
     php artisan monthly:calculate 2026-01
     ```
     e anotar `balance_payer` e `balance_difference` exibidos.
   - Depois do refactor completo, rodar o mesmo comando para o mesmo mês e
     comparar. Os valores devem ser **idênticos** — a regra é a mesma, só
     mudou de lugar.
   - Repetir para pelo menos 2-3 meses diferentes que tenham dados de
     despesa comum, individual e (se houver) dívida cruzada
     (`responsible_for_expense` preenchido e diferente de `who_paid`).

3. **Verificação manual das páginas Filament que dependem do cálculo**
   - `app/Filament/Pages/ComputedResults.php` — abrir no navegador, conferir
     que a tabela renderiza sem erro e os saldos batem com o que era exibido
     antes do refactor.
   - Fluxo de importação de CSV
     (`app/Filament/Resources/CreditCardBills/Pages/CreateCreditCardBill.php`)
     — criar uma fatura com `origin_format = CSV` de teste e conferir que as
     transações são importadas corretamente (campo `who_paid` certo).

4. **Checklist de limpeza**
   - Nenhum `dd()`/`dump()` esquecido nos arquivos alterados.
   - `app/Domain/Finance/Entities/Transaction.php` não tem mais `isShared`
     nem getters com bug de nullability.
   - `app/Services/MonthlyBalanceCalculator.php` não tem mais `DB::table`
     nem `DB::raw`/`selectRaw` para a lógica de saldo (só
     `competenciasDisponiveis()` no repositório Eloquent, que é
     infraestrutura, pode manter SQL).

## Critério de conclusão da task e do refactor como um todo
- Passos 1-4 concluídos sem divergência de comportamento.
- Todas as tasks `task/01-*.md` a `task/10-*.md` marcadas como concluídas
  (atualizar o campo `## Status` de cada arquivo para `Concluída`).
