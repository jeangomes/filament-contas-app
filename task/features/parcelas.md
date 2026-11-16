Analise a migration 
@database/migrations/2026_08_27_163708_add_fields_to_transactions_table.php 
observe os campos installment_current e installment_total 
planeje uma feature que vai alimentar esses campos 
e posteriormente uma tela de acompanhamento de parcelas em andamento.

exemplo de linhas da importação de fatura com parcelas:

2026-08-04,Garmin Proparts Ecomm - Parcela 3/6,"411,50"
2026-08-04,Bodytech Haddock Lobo - Parcela 10/12,"277,00"
2026-08-04,Decolar Com - Parcela 2/12,"908,10"
2026-02-04,EBW*NuViagens - NuPay - Parcela 2/3,456.85
2026-02-04,Bodytech Haddock Lobo - Parcela 4/12,277.00
2026-02-04,L Occitane Au Bresil - Parcela 3/4,56.67
2026-02-04,Mlp*Netshoes - Parcela 5/10,73.57
2026-02-04,Iguaspo*Iguasport - Parcela 5/6,61.66
2026-02-04,Casa e Video - Parcela 3/3,30.99

---

# Implementação

## Status
Concluída — suíte verde, 22 testes novos.

## Decisões

1. **A descrição fica íntegra** (`Garmin Proparts Ecomm - Parcela 3/6`); as
   colunas carregam o dado estruturado em paralelo. Como o nome da série
   precisa ser comparável entre meses, quem o deriva é
   `Parcela::descricaoDaSerie()`, na **leitura** — nada é removido na gravação.
2. **A coluna legada `parcelas` (string) foi removida**: nunca chegou a ser
   preenchida pela importação. Saiu da UI, do `$fillable` e do banco, por
   migration `ALTER` dedicada (editar a migration de `create` não mexe em banco
   já migrado — a armadilha registrada no `CLAUDE.md`).
3. **Backfill por comando** (`transactions:extrair-parcelas`), idempotente e
   com `--dry-run`, para o histórico importado antes da extração existir.
4. **A tela mostra séries em andamento + o compromisso mês a mês** já assumido
   nos meses à frente.

## Formato no extrato

Sufixo ` - Parcela N/M` no fim do `title`, sem variante nos 10 CSVs em
`storage/app/private/imports/csv/` (45 linhas parceladas). A regex **ancora no
fim**, nunca no primeiro ` - `: há descrição com hífen no meio
(`EBW*NuViagens - NuPay - Parcela 2/3`) que seria cortada no lugar errado.

## Dois detalhes que só os dados reais revelaram

**O valor da parcela varia entre meses** (`411,55` → `411,50`; `79,23` →
`79,20`): juros e IOF entram no meio. A projeção do que falta usa o valor da
**parcela mais recente**, não o da primeira.

**Série defasada.** Um parcelamento cuja próxima parcela já deveria ter caído,
mas cuja fatura seguinte nunca foi importada, apareceria como "em andamento"
terminando em 10/2025 — e jogaria meses passados dentro da previsão. Nos CSVs
reais são 10 das 14 séries não quitadas. Daí `Parcelamento::defasadaEm()` e o
corte por competência em `ApuradorDeParcelamentos::projetar($series, $mes)`:
essas séries aparecem na tabela com "sem lançamento desde MM/AAAA" e ficam
**fora** do compromisso futuro. O aviso é o convite a importar a fatura que
falta.

## Onde as coisas ficaram

- `app/Domain/Finance/ValueObjects/Parcela.php` — o VO e o parsing do sufixo
  (mesmo padrão de `ReferenceMonth::fromString()`, que já conhece seu formato).
- `Parcelamento.php` e `CompromissoMensal.php` — a série inteira e o que ela
  compromete num mês.
- `app/Domain/Finance/Services/ApuradorDeParcelamentos.php` — agrupa por
  `descrição da série | quem pagou | total de parcelas` e indexa **pelo número
  da parcela**, para que reimportar a mesma fatura não conte a parcela duas
  vezes (acontece de verdade: há CSVs duplicados em disco).
- `RepositorioDeLancamentos::parcelados()` — porta nova; a série atravessa
  meses, então não dá para consultá-la por competência.
- `TransactionMapper::paraAtributos()` — o mapeamento domínio → Eloquent saiu
  do método privado de `CreateCreditCardBill` para cá, onde dá para testar sem
  exercitar a página Filament.
- `app/Filament/Pages/ParcelasEmAndamento.php` + a view — segue o padrão de
  `BillsSideBySideOverview` (Page + blade à mão): os dados são projeção de
  domínio, não uma query Eloquent.

## O que deliberadamente não mudou

Rateio, custo de vida e fechamento mensal. Uma parcela é uma despesa inteira no
mês dela — `CalculadoraDeSaldoMensal`, `CalculadoraDeCustoDeVida` e
`ApuradorDeFechamentoMensal` não foram tocados.

A fonte da verdade da parcela passa a ser a **coluna**, não a descrição: o
mapper não reparseia o texto. Linha antiga com o sufixo e as colunas vazias é
caso do comando de backfill.
