# Task 05 — Aplicação: serviço de fechamento mensal

## Status
Concluída — 108 testes passando (6 novos). `MonthlySummaryService` removido.

### A remoção não cabia só nesta task
O critério pedia remover `MonthlySummaryService`, mas apagá-lo sozinho
**quebra o `php artisan` inteiro** — inclusive `artisan test`: o construtor de
`CalculateMonthlySummary` injeta a classe, e o container falha ao montar a
lista de comandos. Descoberto testando, não deduzindo.

Solução: religação **mínima** dos dois consumidores (só o type-hint e a
chamada, que agora recebe `ReferenceMonth`), em
`app/Console/Commands/CalculateMonthlySummary.php` e
`app/Filament/Pages/MonthlySummaryPage.php`. O resto deles continua sendo
Task 06 e Task 08.

**Pendência conhecida:** `displaySummary()` do comando ainda lê colunas que
sumiram na Task 04, então o comando persiste certo mas imprime zeros e campos
vazios. Não crasheia (o acessor `referenceMonth` cobre um dos campos e os
demais voltam vazios). Task 06 reescreve essa saída.

### Decisões
- **`calculation_notes` também sobrevive ao recálculo**, junto com
  `difference_paid`. A task só citava o segundo, mas a nota é escrita por
  gente, não pelo cálculo — apagá-la a cada recálculo seria perda de dado.
  Ambos ficam fora do array do `updateOrCreate`, então update preserva e
  create usa o default.
- `createMany()` pela relação em vez de `insert()` em lote: a FK vem da
  relação (e não de atribuição em massa, coerente com a Task 04) e os casts
  são respeitados. São ~2 linhas de participante e uma dezena de casa por mês
  — lote aqui seria otimizar o que não dói.
- Filhas apagadas e recriadas dentro da transação. Sem isso, um recálculo
  interrompido no meio deixaria totais novos com linhas filhas velhas.

### Conferência contra o banco real
`monthly:calculate 2026-07` rodado contra o MySQL, e o resultado conferido com
um SQL independente:

| | SQL direto | persistido |
|---|---|---|
| total comum | 1583,00 | 1583,00 |
| casa | 1200,00 | 1200,00 |

A invariante fecha (1200 casa + 383 comum do cartão de D + 0 do de J = 1583), e
o custo de vida de D bate na mão: cota 791,50 + 138,00 de individual = 929,50.

## Objetivo
Substituir `MonthlySummaryService` por um serviço que apura o mês pelo domínio
e persiste o resultado nas três tabelas, de forma idempotente.

## Arquivos

- `app/Services/FechamentoMensalService.php` (novo)
- `app/Services/MonthlySummaryService.php` (remover ao final)
- `tests/Feature/FechamentoMensalServiceTest.php` (novo)

## Desenho

```php
public function apurarEPersistir(ReferenceMonth $mes): MonthlySummary
```

Fluxo:
1. `$lancamentos = $this->repositorio->paraCompetencia($mes)`
   (`RepositorioDeLancamentos`, do refactor 1 — já resolve a competência).
2. `$fechamento = $this->apurador->apurar($mes, $lancamentos, Participant::cases())`.
3. Dentro de uma transação de banco:
   - `updateOrCreate(['competencia' => $mes->chave().'-01'], [...])` no
     `MonthlySummary`;
   - **apagar e recriar** as linhas filhas (`participantSummaries` e
     `homeExpenses`) — mais simples e correto que tentar casar linha a linha,
     e é o que "cache recalculável" permite;
   - gravar `calculated_at`.
4. Devolver o `MonthlySummary` com as relações carregadas.

`difference_paid` **não** pode ser sobrescrito pelo recálculo: é informação do
usuário, não do cálculo. Preservar o valor existente no `updateOrCreate`.

Conversão de valores: o domínio trabalha em `Money` (centavos); as colunas são
`decimal(10,2)`. Usar `Money::paraDecimal()`, que já devolve a string no
formato da coluna — sem passar por float.

Injetar `RepositorioDeLancamentos` (interface) e `ApuradorDeFechamentoMensal`
por construtor; o binding do repositório já existe em `AppServiceProvider`.

## Testes (feature, `RefreshDatabase`)

1. Persiste o mês: cria 1 `monthly_summaries`, 2 `monthly_participant_summaries`
   (D e J) e as linhas de casa esperadas, com os valores certos.
2. **Idempotência**: rodar duas vezes para a mesma competência não duplica
   nada — segue 1 + 2 + N linhas, com os mesmos valores.
3. Recálculo reflete mudança: alterar uma transação e reapurar atualiza os
   números.
4. `difference_paid = true` sobrevive a um recálculo.
5. Custo de vida ponta a ponta: cenário com casa, cartão dos dois e uma
   individual no cartão do outro com `responsible` preenchido — confere que o
   custo de vida foi para o responsável.
6. Mês sem lançamento: cria o registro zerado, sem `settlement_payer`.

## Critério de conclusão
- `php artisan test --filter=FechamentoMensalServiceTest` verde.
- `MonthlySummaryService` removido e sem referências sobrando
  (`grep -rn MonthlySummaryService app/`) — atenção a
  `app/Filament/Pages/MonthlySummaryPage.php` (Task 08) e
  `app/Console/Commands/CalculateMonthlySummary.php` (Task 06).
