# Task 01 — Domínio: origem do lançamento e responsável efetivo

## Status
Concluída — 81 testes passando (7 novos).

### Decisão
Ficou o `bool $deFatura` (recomendação principal da task), não o enum
`OrigemDoLancamento`: um enum com nome em português conflitaria com a
convenção do `CLAUDE.md` ("nomes de classes/enums em inglês").

### Desvio: o leitor de CSV também precisou mudar
A task dizia que nenhum outro arquivo mudaria, mas `LeitorDeFaturaCsv` é o
**outro produtor** de `Transaction` do domínio — e produz itens de fatura, que
nasceriam com `deFatura` no default `false`. Hoje ninguém lê o flag nesses
objetos (vão direto para atributos do Eloquent em `CreateCreditCardBill`),
então não era bug ativo; mas seria uma armadilha silenciosa na Task 03, onde
`casa = comum && !ehDeFatura` faria itens de cartão importados via CSV
entrarem no total da casa. Corrigido com `deFatura: true` e um teste.

## Objetivo
Dar à entidade de domínio os dois conceitos que faltam para calcular custo de
vida e separar "casa" de "cartão": de onde o lançamento veio, e quem de fato
é responsável por ele.

## Arquivos

- `app/Domain/Finance/Entities/Transaction.php` (alterar)
- `app/Infra/Eloquent/TransactionMapper.php` (alterar)
- `tests/Domain/Finance/Entities/TransactionTest.php` (estender)
- `tests/Infra/Eloquent/TransactionMapperTest.php` (estender)

## Detalhes

### 1. Origem do lançamento
A entidade hoje não sabe se veio de fatura de cartão ou é avulso — o mapper
colapsa isso na data de competência. Precisamos do flag para:
- `casa = comum && !ehDeFatura` (Task 03)
- `cartao_total(P) = soma dos lançamentos de fatura de P` (Task 03)

Adicionar ao construtor (último parâmetro, com default para não quebrar quem
constrói hoje):

```php
private bool $deFatura = false,
```

e o getter:

```php
public function ehDeFatura(): bool
{
    return $this->deFatura;
}
```

Alternativa aceitável, se preferir explicitar: um enum
`OrigemDoLancamento { Fatura, Avulso }` em `app/Domain/Finance/Enums/`. Só
não vale inferir a origem pela data — a competência já foi resolvida no mapper
e a informação original se perde ali.

### 2. Responsável efetivo
```php
public function responsavelEfetivo(): Participant
{
    return $this->responsible ?? $this->whoPaid;
}
```

E `isCrossDebt()` passa a ser escrito sobre ele (comportamento idêntico ao
atual — com `responsible` nulo, o responsável efetivo é quem pagou, então não
há dívida cruzada):

```php
public function isCrossDebt(): bool
{
    return $this->ehDespesaIndividual()
        && $this->responsavelEfetivo() !== $this->whoPaid;
}
```

### 3. Mapper informa a origem
Em `TransactionMapper::paraDominio()`, a relação já está carregada:

```php
deFatura: $model->creditCardBill !== null,
```

## Testes

Em `TransactionTest`:
- `responsavelEfetivo()` devolve `responsible` quando preenchido.
- `responsavelEfetivo()` cai para `whoPaid` quando `responsible` é nulo.
- `isCrossDebt()` continua passando em todos os casos já cobertos (não deve
  precisar alterar nenhuma asserção existente — se precisar, a reescrita mudou
  comportamento e é preciso rever).
- `ehDeFatura()` false por padrão.

Em `TransactionMapperTest`:
- Lançamento com fatura carregada → `ehDeFatura()` true.
- Lançamento avulso (relação carregada como null) → `ehDeFatura()` false.

## Critério de conclusão
- `php artisan test` continua 100% verde (nenhum teste do refactor 1 quebra).
- Nenhum outro arquivo precisa mudar nesta task: os novos membros ainda não
  têm consumidor (chegam nas Tasks 02 e 03).
