# Task 09 — Limpeza do que foi substituído

## Status
Concluída — 129 testes passando. 14 arquivos removidos.

### A conferência de cobertura antes de apagar o teste
A task mandava conferir se algum cenário de `MonthlyBalanceCalculatorTest` só
existia lá. Mapeando os 5:

| cenário | destino |
|---|---|
| formato do array legado | **obsoleto** — o formato morre com `ComputedResults` |
| soma do adiantamento no `total_paid_common` | **obsoleto** — a gambiarra existia só para aquela tela |
| competência pelo vencimento da fatura | já coberto (`RepositorioDeLancamentosEloquentTest`, `TransactionMapperTest`) |
| `pgto_de_fatura` fora do rateio | coberto no domínio, mas **perdia a forma ponta a ponta** |
| dívida cruzada | coberto no domínio, mas **perdia a forma ponta a ponta** |

Os dois últimos foram **migrados** para `FechamentoMensalServiceTest` antes da
remoção, como cenários que saem da coluna do banco e chegam ao persistido:
`pagamento_de_fatura_gravado_no_banco_nao_move_os_numeros` e
`divida_cruzada_gravada_no_banco_vira_acerto`. A cobertura ficou melhor do que
estava, não menor.

### Uma regressão que a limpeza quase escondeu
`BillsSideBySideOverview` — a página que **fica** — consome
`common_amount`/`individual_amount`, que viraram string (cast `decimal:2`) na
Task 07. O blade chama `number_format()` nesses valores. Funciona por coerção
de string numérica, mas isso era raciocínio, não verificação: criei
`BillsSideBySideOverviewTest` renderizando a página com e sem faturas. Passa —
e agora existe um teste que pegaria a quebra se o tipo mudar de novo.

### Verificações
- Nenhuma referência às classes removidas sobrou em `app/`, `resources/`,
  `tests/` ou `routes/` (só dois comentários registrando de onde os testes
  migrados vieram).
- O painel usa `discoverPages`/`discoverResources`, então não havia registro
  explícito a limpar — confirmei lendo o `AdminPanelProvider`.
- Painel conferido programaticamente: o grupo "Resultados/Relatórios" tem
  **exatamente** `MonthlySummaryPage` e `BillsSideBySideOverview`, e
  `MonthlySummaryResource` sumiu da lista de resources (os outros 4 CRUDs,
  não relacionados, seguem lá).
- `monthly:calculate --all` roda contra o MySQL depois da limpeza.
- Removido com `git rm`, para a remoção aparecer no histórico.

## Objetivo
Remover as telas e serviços que a apuração persistida tornou redundantes. Tudo
aqui é código que hoje duplica conceito, calcula em SQL cru ou permite editar
à mão número calculado.

## A remover

### Páginas duplicadas
- `app/Filament/Pages/ComputedResults.php` +
  `resources/views/filament/pages/computed-results.blade.php`
  — "Consolidado (Cálculo dinâmico)": recalcula em SQL cru o que a página nova
  mostra persistido. Tem bloco morto comentado desde antes do refactor 1.
- `app/Filament/Pages/LivingCost.php` +
  `resources/views/filament/pages/living-cost.blade.php`
  — "Custo de Vida": mesma tabela da página nova, com custo de vida calculado
  na view e valores chumbados (`'D' => 78, //rever`).

### CRUD que edita campo calculado
- `app/Filament/Resources/MonthlySummaries/` (diretório inteiro: Resource,
  Pages, Schemas, Tables) — formulário gerado que deixa editar à mão
  `total_home_expenses`, `d_living_cost`, `balance_difference` etc. Com o
  fechamento sendo derivado das transações, editar esses campos só cria
  divergência silenciosa. O que o usuário legitimamente edita
  (`difference_paid`) fica na página nova (Task 08).

### Serviço legado
- `app/Services/MonthlyBalanceCalculator.php` — existia para servir
  `ComputedResults` e `MonthlySummaryService`, ambos removidos. Some junto a
  gambiarra do `total_paid_common` (somar o adiantamento de dívida cruzada ao
  "total pago" para a linha da tela fechar) — ela existia só para aquela
  página.
- `tests/Feature/MonthlyBalanceCalculatorTest.php` — os comportamentos que ele
  cobre (competência de fatura, exclusão de `pgto_de_fatura`, dívida cruzada)
  já estão cobertos no domínio e passam a estar em
  `FechamentoMensalServiceTest` (Task 05). **Conferir isso antes de apagar** —
  se algum cenário só existir aqui, migrar o teste em vez de perdê-lo.

## A manter
- `app/Filament/Pages/BillsSideBySideOverview.php` + blade: resolve outro
  problema (conferir as duas faturas lado a lado). Depende dos totais
  agregados da fatura, corrigidos na Task 07.
- Todo o domínio e infra do refactor 1.

## Checagem antes de apagar

```
grep -rn "MonthlyBalanceCalculator\|ComputedResults\|LivingCost\|MonthlySummaryResource" app/ resources/ tests/ routes/
```

Atenção a registros de navegação/painel: se as páginas estiverem listadas
explicitamente em algum Panel Provider (`app/Providers/Filament/`), remover a
entrada também — senão o painel quebra ao resolver classe inexistente.

## Critério de conclusão
- `php artisan test` verde.
- O grep acima não retorna nada em `app/`, `resources/` e `routes/`.
- Painel abre sem erro e o grupo "Resultados/Relatórios" mostra exatamente
  duas páginas: Consolidado Mensal e Faturas Mensais.
