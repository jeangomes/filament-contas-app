# Task 03 — Domínio: apuração completa do mês

## Status
Concluída — 95 testes passando (6 novos, 45 asserções).
`CalculadoraDeSaldoMensal` ficou intacta (`git diff` vazio), como o critério
exigia: o apurador a consome.

### Decisões
- Saíram 3 VOs em vez de 1: `FechamentoMensal`, `FechamentoDoParticipante` e
  `DespesaDaCasa` (esta última para o detalhe da casa, em vez de arrays
  associativos soltos). Cada uma mapeia numa das três tabelas da Task 04.
- `FechamentoMensal::acertos()` **delega** ao `MonthlyBalance` que o originou,
  em vez de recalcular o acerto — a regra continua num lugar só.
- `FechamentoMensal::de()` lança `LogicException` para participante
  desconhecido, em vez de devolver zero como `MonthlyBalance::saldoDe()` faz.
  A diferença é proposital: o apurador sempre preenche todos os participantes,
  então pedir um que não existe é erro de programação, e em código de dinheiro
  falhar alto é melhor que devolver zero em silêncio.
- **Individual avulsa não é despesa da casa.** A task definia casa como
  "comum sem cartão"; o caso "individual sem cartão" não estava escrito, e
  ficou fora da casa (é gasto pessoal de alguém, não da moradia). Ela continua
  contando no custo de vida do responsável, via Task 02.

### Duplicação da Task 02: mantida
O apurador refiltra a competência e ressoma o total comum, coisa que as duas
calculadoras também fazem internamente. Unificar exigiria que uma delas
recebesse a lista já filtrada, quebrando o contrato "passe qualquer período,
eu filtro" que as duas expõem e que os testes delas cobrem. Três passadas
sobre uma lista de algumas dezenas de lançamentos não justificam esse acoplamento.

## Objetivo
Produzir, numa única passada, tudo o que o fechamento de um mês precisa
persistir e exibir — para que a camada de aplicação não tenha que recompor
nada nem repetir regra.

## Arquivos

- `app/Domain/Finance/ValueObjects/FechamentoMensal.php` (novo)
- `app/Domain/Finance/Services/ApuradorDeFechamentoMensal.php` (novo)
- `tests/Domain/Finance/Services/ApuradorDeFechamentoMensalTest.php` (novo)

## O que o `FechamentoMensal` carrega

Mapeia 1:1 no schema da Task 04 — essa é a intenção do desenho.

```
mes: ReferenceMonth
totalComum: Money
totalCasa: Money
despesasDaCasa: list<{ label: string, valor: Money }>   // agrupadas por description

por participante:
  cartaoTotal, cartaoComum, cartaoIndividual: Money
  pagoComum, cotaComum, ajusteDividaCruzada, saldo: Money
  custoDeVida: Money

acertos(): list<Acerto>
```

Sugestão: em vez de vários arrays soltos, um VO
`FechamentoDoParticipante` por participante, e o `FechamentoMensal` guardando
`array<string, FechamentoDoParticipante>`. Fica mais legível na hora de
persistir e na página.

## Regras a aplicar

Sobre os lançamentos da competência que `contaParaBalanco()`:

- `totalComum` = soma das comuns.
- `casa` = comuns **sem** cartão (`!ehDeFatura()`); `despesasDaCasa` agrupa
  essas por `description`. Sem lista fixa de descrições — a decisão 5 do
  contexto acabou com esse filtro.
- `cartaoTotal(P)` = soma dos lançamentos com `ehDeFatura()` e `whoPaid == P`;
  `cartaoComum(P)` e `cartaoIndividual(P)` idem, filtrando por escopo.
- Saldo, cota, pago e ajuste de dívida cruzada: **reusar
  `CalculadoraDeSaldoMensal`**, que já devolve tudo isso em `MonthlyBalance`
  (`saldoDe`, `totalPagoComumDe`, `cotaComumDe`, `ajusteDeDividaCruzadaDe`).
  Não reimplementar o rateio aqui.
- Custo de vida: `CalculadoraDeCustoDeVida` (Task 02).

O apurador é um compositor: recebe as duas calculadoras por construtor (com
default, como `MonthlyBalanceCalculator` faz hoje) e junta os resultados.

## Invariante que vale testar
`totalCasa + cartaoComum(D) + cartaoComum(J) == totalComum`. Com a definição
nova de casa isso fecha sempre; com a lista fixa antiga, não fechava. É o teste
que prova que a decisão 5 resolveu o problema.

## Testes (puros, sem banco)

1. Mês completo: casa + cartões dos dois + uma dívida cruzada → confere todos
   os campos de ambos os participantes.
2. A invariante acima, incluindo um caso com avulsa comum de descrição
   qualquer (ex.: "Outros"), que na regra antiga escaparia do total da casa.
3. `despesasDaCasa` agrupa duas transações de mesma `description` numa linha
   só, somando.
4. Mês sem nenhum lançamento → tudo zero, `acertos()` vazio, `despesasDaCasa`
   vazio.
5. Lançamento de fatura não entra na casa (mesmo sendo comum).

## Critério de conclusão
- `php artisan test --filter=ApuradorDeFechamentoMensalTest` verde.
- `CalculadoraDeSaldoMensal` não foi alterada (o apurador a consome).
