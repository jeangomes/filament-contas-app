# Task 04 — Persistência: schema e models

## Status
Concluída — 102 testes passando (7 novos). Migration aplicada no banco real.

### A checagem antes de destruir
`monthly_summaries` tinha **0 linhas** no banco real (a única existente, criada
na verificação do refactor 1, já havia sido removida). Nada a perder.

### Bug encontrado ao aplicar: nome de índice > 64 caracteres
A primeira execução **falhou no meio** no MySQL: o nome gerado automaticamente
para o unique composto
(`monthly_participant_summaries_monthly_summary_id_participant_unique`) tem 67
caracteres e estoura o limite de 64 do MySQL. Duas tabelas ficaram criadas, a
terceira não, e a migration nem foi registrada.

**O SQLite não tem esse limite** — o teste de migration em SQLite passou e só
o MySQL reprovou. Vale lembrar sempre que a suíte roda em SQLite e a produção
é MySQL: migration nova merece uma passada no MySQL antes de considerar pronta.

Duas correções:
1. Nome explícito e curto para o índice (`monthly_participant_summaries_unique`).
2. `up()` passou a dropar as **filhas antes da pai**. Sem isso, depois da
   falha parcial a migration virou irrepetível: a FK da filha já criada
   impedia o `dropIfExists` da pai. Agora ela se recupera sozinha.

### Decisões de schema
- Sem índice extra em `monthly_summary_id`: `constrained()` já cria um. Em
  `monthly_participant_summaries` o MySQL aproveita o prefixo à esquerda do
  unique composto e nem cria índice separado — zero índice duplicado.
- Sem índice em `difference_paid`: booleano de baixa cardinalidade quase nunca
  ajuda, e o recálculo faz delete+insert (menos índice = escrita mais rápida).
- `enum('D','J')` mantido para participante, como no resto do schema. O ganho
  da normalização não é "nunca mais migration": é não precisar de 4 colunas
  novas e código novo por participante, como as `d_*`/`j_*` exigiam.
- **FK fora do `$fillable`** nos dois models filhos, como `Transaction` já faz
  com `credit_card_bill_id` — as linhas nascem pela relação. Coberto por teste.
- `decimal:2` em todo dinheiro: o cast devolve string, então nada passa por
  float na volta do banco. Coberto por teste, inclusive com saldo negativo.

### Teste de schema (além do previsto)
A task dizia "nenhum teste próprio", mas as restrições foram cobertas em
`tests/Feature/FechamentoMensalSchemaTest.php`: cascata, os dois uniques,
precisão do dinheiro e a FK fora do fillable. Restrição de banco que ninguém
exercita é só intenção no DDL.

### O que ficou quebrado (esperado, e a suíte não pega)
Estes leem colunas que sumiram, e nenhum tem teste — por isso a suíte segue
verde mesmo assim:
- `app/Services/MonthlySummaryService.php` → substituído na Task 05
- `app/Console/Commands/CalculateMonthlySummary.php` → Task 06
- `app/Filament/Pages/MonthlySummaryPage.php` + blade → Task 08
- `app/Filament/Resources/MonthlySummaries/` → removido na Task 09

`ComputedResults` **não** quebrou: o `balance_payer` que ele usa vem do array
do `MonthlyBalanceCalculator`, não do model.

## Objetivo
Substituir a tabela larga `monthly_summaries` (colunas fixas `d_*`/`j_*` e uma
coluna por categoria da casa) por um modelo normalizado que aguenta um terceiro
participante e categorias novas sem migration.

## ⚠️ Migration destrutiva
A migration **recria** `monthly_summaries`, descartando as colunas antigas.
É aceitável porque a tabela é um cache recalculável (decisão 3) e hoje tem 1
linha — criada na verificação do refactor 1. Repovoar com
`monthly:calculate --all` (Task 06) faz parte da entrega.

Antes de rodar, conferir quantas linhas existem no banco real:
`docker compose exec db mysql -uroot -proot laravel_db -e "SELECT COUNT(*) FROM monthly_summaries;"`
Se aparecer histórico que não dá para reconstruir, parar e reavaliar.

## Schema

```php
Schema::create('monthly_summaries', function (Blueprint $table) {
    $table->id();
    $table->date('competencia')->unique();       // mês em que se paga / vence
    $table->decimal('total_home_expenses', 10, 2)->default(0);
    $table->decimal('total_common_expenses', 10, 2)->default(0);
    $table->decimal('settlement_amount', 10, 2)->default(0);   // sempre >= 0
    $table->enum('settlement_payer', ['D', 'J'])->nullable();  // quem paga
    $table->boolean('difference_paid')->default(false);
    $table->timestamp('calculated_at')->nullable();
    $table->text('calculation_notes')->nullable();
    $table->timestamps();
});

Schema::create('monthly_participant_summaries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('monthly_summary_id')->constrained()->cascadeOnDelete();
    $table->enum('participant', ['D', 'J']);
    $table->decimal('card_total', 10, 2)->default(0);
    $table->decimal('card_common', 10, 2)->default(0);
    $table->decimal('card_individual', 10, 2)->default(0);
    $table->decimal('paid_common', 10, 2)->default(0);
    $table->decimal('share_common', 10, 2)->default(0);
    $table->decimal('cross_debt_adjustment', 10, 2)->default(0);
    $table->decimal('balance', 10, 2)->default(0);      // pode ser negativo
    $table->decimal('living_cost', 10, 2)->default(0);
    $table->timestamps();
    $table->unique(['monthly_summary_id', 'participant']);
});

Schema::create('monthly_home_expenses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('monthly_summary_id')->constrained()->cascadeOnDelete();
    $table->string('label');                            // a description da transação
    $table->decimal('amount', 10, 2)->default(0);
    $table->timestamps();
    $table->index('monthly_summary_id');
});
```

Colunas que **somem** de `monthly_summaries`: `reference_month`,
`due_payment_month`, `house_rental`, `condominium`, `eventual_apartment`,
`electricity_bill`, `gas_bill`, `internet_bill`,
`total_home_expenses_per_person` (derivável), `balance_difference`,
`balance_payer`, todas as `d_*`/`j_*`, `is_calculated` (sem sentido quando tudo
é sempre calculado).

## Models

- `app/Models/MonthlySummary.php` (reescrever): `competencia` com cast `date`,
  `settlement_payer` idealmente com cast para o enum `Participant`, relações
  `participantSummaries()` e `homeExpenses()` (`HasMany`). Acessores úteis para
  a página: `referenceMonth` (competência menos 1 mês, só exibição) e
  `balanceDescription` (reaproveitar o texto atual "X deve pagar R$ Y", que a
  UI já usa).
- `app/Models/MonthlyParticipantSummary.php` (novo) e
  `app/Models/MonthlyHomeExpense.php` (novo): `BelongsTo` para
  `MonthlySummary`, casts `decimal:2`, `participant` como enum.
- Manter o padrão dos outros models: `protected function casts(): array`.

Atenção: `AppServiceProvider::boot()` liga
`Model::preventAccessingMissingAttributes()` e `preventLazyLoading()` — a
página vai precisar de eager load das relações.

## Nota sobre o que quebra aqui
Depois desta task, `MonthlySummaryService`, `MonthlySummaryPage`, sua blade e o
CRUD `app/Filament/Resources/MonthlySummaries/` referenciam colunas que não
existem mais. É esperado: as Tasks 05, 08 e 09 os substituem/removem. Se
preferir não deixar a árvore quebrada, faça 04+05+08+09 numa tacada.

## Testes
Nenhum teste próprio; a cobertura vem na Task 05. Rode
`php artisan migrate:fresh` num banco descartável para confirmar que as três
migrations sobem limpas (e em SQLite, que é o que os testes usam).

## Critério de conclusão
- Migrations sobem e descem sem erro (`migrate` e `migrate:rollback`).
- Models com relações resolvendo nos dois sentidos.
