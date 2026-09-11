# Task 08 — UI: página "Consolidado Mensal"

## Status
Concluída — 130 testes passando (6 novos). Página renomeada para
"Consolidado Mensal" (era "Consolidado (Armazenado)").

### A verificação virou automatizada
A task previa "verificar no navegador". Em vez disso, os critérios de
conclusão viraram teste com `Livewire::test()`: renderiza vazia, renderiza com
histórico, a ação de calcular persiste, e recalcular tudo preserva o `Pago`.
Isso pega exatamente o que o projeto tem de armadilha — `preventLazyLoading()`
e `preventAccessingMissingAttributes()` estouram na renderização real.

**Alcance do teste:** `Livewire::test()` monta o componente direto, **sem
passar pelo middleware do painel**. Os testes cobrem renderização e ações, não
a autorização — que continua sendo do painel (`->login()` + `authMiddleware` +
`User::canAccessPanel()`, hoje restrito a dois e-mails).

### Guarda de N+1
`a_tabela_nao_faz_consulta_por_linha` compara o número de consultas para
renderizar 1 competência contra 6: se as colunas por participante fossem
buscar a relação linha a linha, a diferença apareceria. **Verifiquei que o
teste sabe falhar**: removendo o `->with([...])` da query, ele reprova (o
teste genérico de renderização, esse, passou mesmo sem eager load — daí valer
um guarda dedicado).

### Decisões de UI
- A tabela mostra o essencial (casa, total comum, custo de vida e cartão de
  cada um, acerto) e esconde saldo e `calculated_at` atrás de
  `toggleable(isToggledHiddenByDefault: true)`. Oito colunas por participante
  numa tabela só viram ruído.
- `difference_paid` é `ToggleColumn` — edição inline, e é o **único** campo
  editável da tela, porque é o único que não é calculado.
- O "Detalhe" usa `RepeatableEntry` sobre as relações, mostrando as despesas
  da casa e os números completos por participante. Substitui o `ViewAction` do
  CRUD que a Task 09 remove.
- O texto do acerto vem do acessor `balance_description` — a mesma frase que o
  comando imprime.
- Erros nas ações vão para `report()` (log) e chegam ao usuário como
  Notification, sem stack trace na tela.
- O select de competência ganhou aviso: "o mês do vencimento das faturas —
  não o mês em que os gastos aconteceram".

### Blade
Não consulta mais o banco: a view recebe o último fechamento de um
`#[Computed]` da classe da página (antes chamava `MonthlySummary::latest()`
duas vezes dentro do template). Os cards e o mini-gráfico que liam colunas
removidas foram reescritos sobre as relações.

As duas ocorrências de `reference_month` que restam são o **acessor** derivado
(competência menos um mês), não a coluna removida.

## Objetivo
Uma página só, lendo do persistido, que responde as duas perguntas do sistema:
**quanto a vida de cada um custou** e **quem paga quanto a quem** — no lugar
das três telas de hoje (`MonthlySummaryPage`, `ComputedResults`, `LivingCost`).

## Arquivos

- `app/Filament/Pages/MonthlySummaryPage.php` (reescrever; renomear para
  `ConsolidadoMensal`/`MonthlyOverview` se preferir — é o nome que aparece na
  navegação, hoje "Consolidado (Armazenado)")
- `resources/views/filament/pages/monthly-summary-page.blade.php` (reescrever)

## Conteúdo

Tabela sobre `MonthlySummary::query()->with(['participantSummaries', 'homeExpenses'])`
— o eager load não é opcional: `AppServiceProvider::boot()` liga
`Model::preventLazyLoading()` fora de produção, então lazy load estoura.

Colunas, uma linha por competência:
- Competência (e "ref." derivada = competência menos 1 mês, só exibição)
- Total casa | Total comum
- Por participante (D e J): cartão total, comum, individual, **custo de vida**
- Acerto: quem paga e quanto (o acessor `balanceDescription` do model já
  monta o texto "X deve pagar R$ Y")
- `difference_paid` como toggle/badge — é o único campo que o usuário edita

Para as colunas por participante a partir da relação, o caminho mais simples é
um acessor no `MonthlySummary` (ex.: `participantSummary('D')`) ou colunas
`TextColumn` com `state(fn (MonthlySummary $r) => ...)`.

### Ações de cabeçalho
- **Calcular mês**: select de competência (aproveitar `getMonthOptions()`, que
  já existe na página atual) → `FechamentoMensalService::apurarEPersistir()`,
  com `Notification` de sucesso/erro como hoje.
- **Recalcular todos**: `requiresConfirmation()`, percorrendo
  `competenciasDisponiveis()`. Como é cache recalculável (decisão 3), não há
  risco de perder dado — mas `difference_paid` precisa sobreviver (Task 05).

### Detalhe do mês
Substituir o `ViewAction` do CRUD removido (Task 09) por um modal/infolist
mostrando o detalhe da casa (linhas de `monthly_home_expenses`) e os números
completos de cada participante. É o que a instrução "Visualize despesas por
categoria" da blade atual promete.

## O que remover da blade atual
Os cards do topo e o mini-gráfico leem colunas que deixaram de existir
(`d_credit_card_total`, `j_credit_card_total`, `d_living_cost`,
`j_living_cost`, `reference_month`). Reescrever sobre as relações. O bloco
"💡 Como Usar" pode ficar, atualizando o texto.

Também: a blade instancia `MonthlySummary::latest()` direto na view — prefira
passar os dados pela classe da página (a view fica burra e testável).

## Verificação
Sem teste automatizado de UI neste projeto; verificar no navegador
(`http://localhost:8000`, grupo "Resultados/Relatórios"):
- a tabela lista os meses com os valores batendo com `monthly:calculate`;
- calcular e recalcular funcionam e refletem na hora;
- o detalhe do mês abre e mostra as despesas da casa.

## Critério de conclusão
- Página renderiza sem erro com histórico populado **e** com a tabela vazia
  (estado inicial, que a blade atual já trata).
- Nenhuma referência a coluna que não existe mais.
