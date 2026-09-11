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

# Arquitetura
- `app/Domain/Finance/` — domínio puro: sem Eloquent, sem SQL, sem facades.
  Entidade `Transaction`, value objects (`Money`, `ReferenceMonth`,
  `MonthlyBalance`, `Acerto`, `FechamentoMensal`, `FechamentoDoParticipante`,
  `DespesaDaCasa`), enums, e os serviços: `CalculadoraDeSaldoMensal` (rateio e
  acerto), `CalculadoraDeCustoDeVida` e `ApuradorDeFechamentoMensal`, que
  compõe os dois. É aqui que a regra de negócio deve ser escrita e testada.
- `app/Domain/**/Repositories/` e `app/Domain/FonteDeLancamentos.php` — portas
  (interfaces) que o domínio consome.
- `app/Infra/` — adapters: `Csv/` (importação) e `Eloquent/` (repositório +
  mapper). Bindings em `AppServiceProvider::register()`.
- `app/Services/FechamentoMensalService.php` — única orquestração: apura pelo
  domínio e persiste. Não contém regra.
- `app/Observers/TransactionObserver.php` — mantém os totais agregados da
  fatura (`common_amount`/`individual_amount`), que são cache de exibição.
- `app/Filament/` — UI. Duas páginas: `MonthlySummaryPage` ("Consolidado
  Mensal", o histórico persistido) e `BillsSideBySideOverview` (faturas lado a
  lado).

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
Dois ciclos concluídos, documentados task a task com as decisões tomadas:
- `task/00-contexto.md` a `task/11-*.md` — refactor 1: rateio e acerto saíram
  do SQL para o domínio.
- `task/refactor-02/` — refactor 2: custo de vida, persistência normalizada do
  histórico e consolidação das telas.

Pendências conhecidas:
- O rename de colunas de `credit_card_bills` (`title_description_owner` →
  `card_name`, `amount` → `total_amount`, `common_amount` → `amount_shared`)
  segue **revertido**. Foi editado direto na migration de `create`, o que não
  altera banco já migrado e quebrava os testes. Refazer com migration `ALTER`
  dedicada, atualizando junto model, factory e Filament.
- `CreditCardBill::$casts` ainda tem `'amount' => 'float'` — é a última coluna
  monetária do projeto que passa por float. As outras usam `decimal:2`.
- `User::canAccessPanel()` libera o painel por dois e-mails chumbados no código.
