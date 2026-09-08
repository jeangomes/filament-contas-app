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

Lançamentos vêm de duas origens: itens de fatura de cartão (importados de CSV)
e transações avulsas.

**Regra de competência (não óbvia):** um item de fatura pertence ao mês de
`due_date` da fatura, não ao de `transaction_date`. Lançamento avulso usa a
própria `transaction_date`. Isso está centralizado em
`app/Infra/Eloquent/TransactionMapper.php`.

**Lançamentos do tipo `pgto_de_fatura` não entram no rateio** (é só o pagamento
do cartão, não uma despesa).

# Arquitetura (refactor DDD em andamento)
- `app/Domain/Finance/` — domínio puro: sem Eloquent, sem SQL, sem facades.
  Entidade `Transaction`, value objects (`Money`, `ReferenceMonth`,
  `MonthlyBalance`, `Acerto`), enums, e o serviço `CalculadoraDeSaldoMensal`
  (onde mora a regra de rateio/acerto). É aqui que a regra de negócio deve ser
  escrita e testada.
- `app/Domain/**/Repositories/` e `app/Domain/FonteDeLancamentos.php` — portas
  (interfaces) que o domínio consome.
- `app/Infra/` — adapters: `Csv/` (importação) e `Eloquent/` (repositório +
  mapper). Bindings em `AppServiceProvider::register()`.
- `app/Services/` — orquestração legada, sendo esvaziada. `MonthlyBalanceCalculator`
  já só orquestra o domínio e monta o array no formato que a página
  `ComputedResults` consome.
- `app/Filament/` — UI. As páginas `LivingCost.php` e `ComputedResults.php` são
  legadas, com lógica duplicada e código morto — não usar como referência.

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
  `$fillable`, de propósito.

# Estado do refactor
As etapas estão documentadas em `task/00-contexto.md` até `task/11-*.md`, com
status e decisões tomadas. As 11 tasks estão concluídas.

Pendências conhecidas (fora do escopo do que foi feito):
- `MonthlySummaryService::calculateHomeExpenses()` e `calculateCreditCardData()`
  ainda usam `DATE_FORMAT` (MySQL), o que impede testar o resumo mensal ponta a
  ponta. Trocar por `whereYear`/`whereMonth` destrava.
- Havia um rename de colunas de `credit_card_bills` (`title_description_owner`
  → `card_name`, `amount` → `total_amount`, `common_amount` → `amount_shared`)
  editado direto na migration de `create`, o que não altera um banco já
  migrado e quebrava os testes. Foi revertido; refazer com uma migration
  `ALTER` dedicada, atualizando junto model, factory, services e Filament.
- A página `ComputedResults` exibe "pagou X, deveria pagar Y, saldo Z" e essa
  conta só fecha porque o valor adiantado de dívida cruzada é somado ao
  "total pago" na montagem do array legado (`MonthlyBalanceCalculator`). O
  domínio mantém os dois conceitos separados — não "simplifique" isso sem
  ajustar a página.
