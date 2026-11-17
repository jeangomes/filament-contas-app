# Code style
- Use PHP moderno e tipagem.
- Na camada de domínio (`app/Domain`): `declare(strict_types=1)`, classes
  `final readonly` para value objects, construtor com propriedades promovidas.
- Nomes de métodos do domínio em português (`ehDespesaComum()`, `somar()`,
  `paraDecimal()`, `dividirIgualmente()`), seguindo o que já existe. Nomes de
  classes/enums em inglês (`Transaction`, `Participant`, `MonthlyBalance`).

# About environment
- to run artisan commands: `docker compose exec app php artisan`
- Não há PHP no host — tudo roda no container `contas-casinha-app`.
- Testes: `docker compose exec app php artisan test`. Usam SQLite em memória
  (ver `phpunit.xml`), então **nada de SQL específico do MySQL** em código que
  precise ser testado: `DATE_FORMAT`/`IF` quebram os testes. Use
  `whereYear`/`whereMonth`/`COALESCE`.
- Os testes rodam com `APP_LOCALE=pt_BR` (`phpunit.xml`), igual ao `.env`:
  `->money('BRL')` sai `R$ 150,00` como na tela, então dá para afirmar o
  valor formatado.
- Banco real: MySQL no container `contas-casinha-db` (`laravel_db`).

# O que o sistema faz
Duas pessoas (D e J) dividem despesas. Cada mês gera um balanço:
1. As despesas **comuns** do mês são somadas e rateadas em partes iguais —
   quem pagou acima da própria cota fica com saldo positivo (a receber).
2. Despesas **individuais** pagas por quem não é o responsável ("dívida
   cruzada") creditam quem pagou e debitam o responsável.
3. O acerto final: quem está com saldo positivo recebe a diferença de quem
   está negativo.

Além do acerto, cada mês apura o **custo de vida** de cada participante:

```
custo de vida (P) = cota de P no total das despesas comuns
                  + despesas individuais de responsabilidade de P
```

São perguntas diferentes e **não se somam**: o custo de vida diz quanto a vida
de cada um custou; o saldo diz quem adiantou dinheiro e quem ficou devendo.
Invariante útil: a soma dos custos de vida do mês é igual ao total gasto no mês.

**Responsável efetivo:** o responsável por uma despesa individual é
`responsible_for_expense` quando preenchido, **senão quem pagou**. Sem esse
fallback nenhuma individual entraria em custo de vida nenhum, porque hoje a
coluna está quase toda nula.

Lançamentos vêm de duas origens: itens de fatura de cartão (importados de CSV)
e transações avulsas.

**Regra de competência (não óbvia):** um item de fatura pertence ao mês de
`due_date` da fatura, não ao de `transaction_date`. Lançamento avulso usa a
própria `transaction_date`. Isso está centralizado em
`app/Infra/Eloquent/TransactionMapper.php`.

**Lançamentos do tipo `pgto_de_fatura` não entram no rateio** (é só o pagamento
do cartão, não uma despesa).

**"Despesa da casa" = despesa comum que não veio de fatura de cartão.** Não há
lista de descrições: qualquer comum avulsa é da casa. Daí vale a invariante
`casa + comum dos cartões == total comum`, que a versão antiga (com 6
descrições fixas) não fechava.

**Parcelas.** O extrato traz a parcela como sufixo da descrição
(`Garmin Proparts Ecomm - Parcela 3/6`). A importação extrai `N/M` para
`installment_current`/`installment_total` e **mantém a descrição íntegra**; o
nome da série (sem o sufixo) é derivado na leitura por
`Parcela::descricaoDaSerie()`. Uma série é `descrição | quem pagou | total de
parcelas`. Parcela não muda rateio nem custo de vida: é despesa inteira no mês
dela. Histórico importado antes disso se conserta com
`transactions:extrair-parcelas`. Detalhes e decisões em
`task/features/parcelas.md`.

# Arquitetura
- `app/Domain/Finance/` — domínio puro: sem Eloquent, sem SQL, sem facades.
  Entidade `Transaction`, value objects (`Money`, `ReferenceMonth`,
  `MonthlyBalance`, `Acerto`, `FechamentoMensal`, `FechamentoDoParticipante`,
  `DespesaDaCasa`, `Parcela`, `Parcelamento`, `CompromissoMensal`), enums, e os
  serviços: `CalculadoraDeSaldoMensal` (rateio e acerto),
  `CalculadoraDeCustoDeVida` e `ApuradorDeFechamentoMensal`, que compõe os
  dois, mais `ApuradorDeParcelamentos` (séries parceladas, à parte do
  fechamento). É aqui que a regra de negócio deve ser escrita e testada.
  `Transaction::rubrica()` (enum `Rubrica`) dá nome ao caminho de cada
  lançamento no fechamento — comum de cartão, casa, individual, dívida cruzada
  ou fora do rateio. Não é regra nova: combina os predicados da entidade. As
  calculadoras **não** a usam; `TransactionRubricaTest` é o que impede as duas
  leituras de divergirem.
- `app/Domain/**/Repositories/` e `app/Domain/FonteDeLancamentos.php` — portas
  (interfaces) que o domínio consome.
- `app/Infra/` — adapters: `Csv/` (importação), `Eloquent/` (repositório +
  mapper) e `Excel/` (exportação do relatório, com openspout). Bindings em
  `AppServiceProvider::register()`.
  `RepositorioDeLancamentosEloquent::consultaDaCompetencia()` devolve o Builder
  dos models da competência; fica fora da porta (o domínio não conhece
  Builder) e é o único lugar da regra de competência em SQL.
- `app/Services/FechamentoMensalService.php` — orquestração de escrita: apura
  pelo domínio e persiste. Não contém regra.
- `app/Services/RelatorioDaCompetenciaService.php` — o par de leitura: monta o
  `Relatorio\RelatorioDaCompetencia` (lançamentos + fechamento apurado ao vivo
  + fechamento salvo, para comparar). Não persiste nem contém regra. No read
  model, os **resultados vêm do `FechamentoMensal`** e as somas das linhas
  servem a subtotais e às `conferencias()` — dois caminhos independentes, senão
  a conferência é tautologia.
- `app/Observers/TransactionObserver.php` — mantém os totais agregados da
  fatura (`common_amount`/`individual_amount`), que são cache de exibição.
  **Recalcula só no `updated`**: importar e apagar não disparam recálculo, para
  não pagar um SUM por linha na importação. Logo após importar uma fatura os
  totais ficam em zero até alguém editar alguma transação dela — o que não
  afeta cálculo nenhum, porque o fechamento soma as transações pelo domínio.
- `app/Filament/` — UI. O "Consolidado Mensal" (histórico persistido) é o
  `MonthlySummaryResource`, somente leitura: listagem com as ações de
  calcular/recalcular, widget `UltimoFechamento` no topo e página de detalhe —
  tudo com componentes nativos, sem view Blade. O eager load das relações fica
  em `getEloquentQuery()`, que serve à tabela e ao route binding do detalhe.
  `ParcelasEmAndamento` ("Parcelas") é Page, não Resource, porque as séries
  não têm model (projeção pura, nada persistido): `content()` com resumo,
  tabela Filament alimentada pelo domínio via `->records()` e projeção em
  infolist — também sem Blade. Os filtros da tabela valem para os três blocos.
  Atenção: **não use `#[Computed]` para nada que dependa de filtro de
  tabela.** O filtro "deferred" e o clique em Aplicar chegam na mesma
  request; algo lê o valor antes de `applyTableFilters()`, o cache guarda o
  resultado do filtro antigo e a tela só responde no segundo clique. Cacheie
  só o que independe do filtro (consulta + apuração, em propriedade privada)
  e filtre a cada leitura. `filterTable()` nos testes não pega isso — use
  `->update(calls: [applyTableFilters], updates: [tableDeferredFilters...])`.
  Padrão do projeto: **não usar `#[Computed]` nas páginas/widgets.** Além
  da armadilha acima, ele só usa cache lido como propriedade mágica
  (`$this->x`), que a IDE não enxerga. Use método privado comum e, se a
  consulta for cara, cache explícito em propriedade privada (o Livewire não
  serializa privadas, então vale por request) — ver `LancamentosDaFatura`.
  `RelatorioDaCompetencia` ("Relatório Mensal") é a memória de cálculo de uma
  competência (`#[Url]`, `Y-m`): resumo, rateio, dívidas cruzadas, saldo e
  acerto, custo de vida, origem, as conferências e a tabela de todos os
  lançamentos com a rubrica. Calculada ao vivo — funciona para mês não
  fechado — e avisa quando o fechamento salvo está desatualizado, com ação de
  recalcular. Os filtros da tabela valem **só para a tabela** (ao contrário
  de Parcelas). "Exportar Excel" grava o mesmo read model em `.xlsx`, sempre o
  mês inteiro. O cache do relatório é guardado junto com o mês: uma action
  troca o mês depois de algo já tê-lo lido na mesma request.
  `VisaoAnual` ("Visão Anual") lê **só o fechamento persistido** (nada ao
  vivo): por ano (`#[Url]`, navegável), os 12 meses de competência com
  fatura total/comum/individual e custo de vida de cada participante, mais
  a linha "Total do ano" (só meses fechados). Mês sem fechamento aparece
  marcado "Sem fechamento", não some.
  Tabela com `->records()` (dados próprios) **não tem summarizer, busca,
  ordenação nem paginação automáticos**: filtre em PHP e ponha o total na
  descrição da tabela.
  Formatação de `Money`/competência nas páginas: trait
  `app/Filament/Concerns/FormataValores.php`.
  `BillsSideBySideOverview` ("Faturas Mensais") guarda só o mês (`#[Url]`,
  `Y-m`) e monta um `LancamentosDaFatura` (`TableWidget` em
  `app/Filament/Pages/Widgets/`, fora de `Filament/Widgets` para não cair no
  Dashboard) por participante, com chave que inclui o mês. Os totais são
  `Sum` sobre as transações, não o cache `common_amount`/`individual_amount`.
- **Front sem build.** Não há Vite, Tailwind compilado, `package.json` nem
  Node no projeto, e nenhuma página tem view Blade. O CSS próprio é
  `resources/css/admin.css`, puro, registrado com `FilamentAsset` em
  `AppServiceProvider::boot()` e publicado em `public/css/app/` pelo
  `php artisan filament:assets` (roda no `composer install/update`). Depois de
  editar o CSS, rode esse comando. Use só hooks `fi-*`, classes próprias e as
  variáveis do painel (`var(--gray-200)`): **classe utilitária do Tailwind que
  o Filament não usa não existe** no CSS carregado, e `@apply` não funciona.

# Fechamento mensal persistido
Três tabelas, chaveadas pela **competência** (o mês do vencimento):
`monthly_summaries` (totais do mês e o acerto), `monthly_participant_summaries`
(uma linha por mês × participante) e `monthly_home_expenses` (detalhe da casa,
uma linha por descrição).

É **cache recalculável**: `monthly:calculate <YYYY-MM>` ou `--all` reconstrói
tudo a partir das transações, sem duplicar. As duas únicas informações do
usuário — `difference_paid` e `calculation_notes` — sobrevivem ao recálculo.

⚠️ O comando recebe a **competência** (mês do vencimento), não o antigo "mês de
referência". O formato é o mesmo, então o erro passa despercebido: quem fechava
julho com `2026-06` agora usa `2026-07`.

**Dinheiro sempre em centavos (inteiro), via `Money`.** Nunca float: divisão em
float gera centavos fantasmas. Para ratear use `Money::dividirIgualmente()`,
que distribui o resto e garante que a soma bata com o total.

# Testes
- PHPUnit (não Pest), atributo `#[Test]`, métodos em snake_case e português
  (`quem_paga_tudo_recebe_metade_do_outro`), classes `final`, sem mocks.
- Testsuites em `phpunit.xml`: `Unit`, `Feature`, `Domain`, `Infra`.
- Testes de `Domain` estendem `PHPUnit\Framework\TestCase` e **não podem tocar
  o banco**. Testes que precisam de banco estendem `Tests\TestCase` com
  `RefreshDatabase`.
- Ao criar transação ligada a fatura nos testes, use a relação
  (`$fatura->transactions()->create(...)`) — `credit_card_bill_id` não está no
  `$fillable`, de propósito. Mesma coisa para as linhas filhas do fechamento.
- Páginas Filament são testadas com `Livewire::test(Pagina::class)`, o que pega
  violação de `preventLazyLoading()` e coluna inexistente. Atenção ao alcance:
  isso monta o componente **sem** passar pelo middleware do painel, então não
  cobre autorização.

# Estado do refactor
Dois ciclos e duas features concluídos, documentados task a task com as decisões tomadas:
- `task/00-contexto.md` a `task/11-*.md` — refactor 1: rateio e acerto saíram
  do SQL para o domínio.
- `task/refactor-02/` — refactor 2: custo de vida, persistência normalizada do
  histórico e consolidação das telas.
- `task/features/parcelas.md` — feature de parcelas: extração na importação,
  backfill e a tela de acompanhamento.
- `tasks/report/` — relatório da competência: rubrica no domínio, serviço de
  leitura, a página "Relatório Mensal" e a exportação para Excel.

Pendências conhecidas:
- O rename de colunas de `credit_card_bills` (`title_description_owner` →
  `card_name`, `amount` → `total_amount`, `common_amount` → `amount_shared`)
  segue **revertido**. Foi editado direto na migration de `create`, o que não
  altera banco já migrado e quebrava os testes. Refazer com migration `ALTER`
  dedicada, atualizando junto model, factory e Filament.
- `CreditCardBill::$casts` ainda tem `'amount' => 'float'` — é a última coluna
  monetária do projeto que passa por float. As outras usam `decimal:2`.
- `User::canAccessPanel()` libera o painel por dois e-mails chumbados no código.
