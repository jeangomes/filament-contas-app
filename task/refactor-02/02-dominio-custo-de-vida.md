# Task 02 — Domínio: cálculo do custo de vida

## Status
Concluída — 89 testes passando (8 novos).

Ficou como serviço próprio (`CalculadoraDeCustoDeVida`), não como método
privado do apurador. A Task 03 vai compor os dois; se lá ficar mais natural
colapsar, o teste desta task continua valendo como cobertura da fórmula.

Sobra uma duplicação pequena e consciente com `CalculadoraDeSaldoMensal`: as
duas filtram a competência e somam o total comum. Vale reavaliar na Task 03,
quando o apurador tiver os dois na mão — só não vale unificar às custas de
deixar a fórmula fora do domínio.

## Objetivo
Implementar, como regra de domínio pura e testável, o custo de vida mensal de
cada participante — a resposta para "quanto a vida de cada um custou neste
mês", independente de quem adiantou o dinheiro.

## Fórmula

```
custo_de_vida(P) = total_comum_do_mes / N
                 + soma das individuais cujo responsável efetivo é P
```

Onde:
- `total_comum_do_mes` = todas as despesas comuns da competência (casa +
  cartões + avulsas), já excluindo `pgto_de_fatura` via `contaParaBalanco()`.
- `N` = número de participantes.
- "responsável efetivo" = `responsible ?? whoPaid` (Task 01).

Note que a divisão do comum usa `Money::dividirIgualmente()` (Task 04 do
refactor 1), não divisão float — pelo mesmo motivo de sempre: não inventar nem
perder centavo.

## O que a fórmula corrige em relação ao código atual
`MonthlySummaryService::calculateLivingCost()` faz
`casa/2 + cartao[P].individual + (cartao[D].comum + cartao[J].comum)/2`, o que:
- ignora despesas individuais avulsas (fora do cartão);
- atribui a individual a quem pagou, não a quem é responsável;
- depende das colunas agregadas da fatura, desatualizadas na importação de CSV
  (ver Task 07).

## Arquivos

- `app/Domain/Finance/Services/CalculadoraDeCustoDeVida.php` (novo)
- `tests/Domain/Finance/Services/CalculadoraDeCustoDeVidaTest.php` (novo)

Assinatura sugerida, espelhando `CalculadoraDeSaldoMensal`:

```php
/**
 * @param  list<Transaction>  $lancamentos
 * @param  list<Participant>  $participantes
 * @return array<string, Money>  Chave = Participant::value
 */
public function calcular(ReferenceMonth $mes, array $lancamentos, array $participantes): array
```

Se na Task 03 ficar mais natural que isso seja um método privado do
`ApuradorDeFechamentoMensal`, tudo bem colapsar — o que não pode é a fórmula
acabar fora do domínio.

## Testes (puros, sem banco)

1. Só despesas comuns: cada um paga metade, custo de vida igual para os dois,
   independente de quem pagou.
2. Individual no próprio cartão → entra no custo de vida do dono.
3. Individual no cartão do outro com `responsible` preenchido → entra no custo
   de vida do **responsável**, não do dono do cartão.
4. Individual avulsa (sem cartão) → entra no custo de vida.
5. Individual sem `responsible` → entra no custo de vida de quem pagou
   (fallback do responsável efetivo).
6. `pgto_de_fatura` → ignorado.
7. Lançamento de outra competência → ignorado.
8. Total comum com centavo ímpar → soma dos custos de vida individuais menos
   as individuais fecha exatamente com o total comum (sem centavo perdido).

## Critério de conclusão
- `php artisan test --filter=CalculadoraDeCustoDeVidaTest` verde.
- Nenhum `Illuminate\*`, `DB` ou model Eloquent importado no teste nem no
  serviço.
