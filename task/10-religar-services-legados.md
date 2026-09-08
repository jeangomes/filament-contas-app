# Task 10 — Religar MonthlyBalanceCalculator e MonthlySummaryService

## Status
Concluída — 73 testes passando.

## Decisões tomadas na implementação
- `MonthlyBalance` ganhou `totalPagoComumDe()` / `cotaComumDe()` (parâmetros
  de construtor opcionais, preenchidos pela calculadora). Isso evita
  recalcular o rateio fora do domínio só para preencher as chaves
  `total_paid_common` / `share_common` que a blade `computed-results` exibe.
- `MonthlyBalanceCalculator` ganhou `saldoDoMes(string): MonthlyBalance`, que
  apura um único mês — usado por `MonthlySummaryService`, que antes varria o
  histórico inteiro só para filtrar um mês.
- `calculateBalances()` agora devolve `difference` sempre positivo (vindo de
  `Acerto::$valor`), com `payer` identificando quem paga. O `abs()` na linha
  64 de `calculateAndPersistSummary()` continua lá, inofensivo.
- Testes de integração novos em `tests/Feature/MonthlyBalanceCalculatorTest.php`
  cobrem o caminho real (banco → repositório → mapper → domínio → array
  legado), inclusive dívida cruzada e exclusão de `pgto_de_fatura`.

## Trade-off conhecido
`calculateFinalBalances()` agora faz uma query por mês de competência
(`competenciasDisponiveis()` + um `paraCompetencia()` por mês), enquanto a
versão em SQL fazia 2 queries no total. Para o volume deste app (2 pessoas,
algumas dezenas de meses) é irrelevante, mas se incomodar, dá para trocar por
um `todos()` no repositório e deixar a calculadora filtrar por mês em PHP —
ela já faz esse filtro internamente.

## Descoberta: MonthlySummaryService ainda não é testável ponta a ponta
Tentei cobrir `calculateAndPersistSummary()` com teste de feature, mas ele
falha em SQLite por causa de `DATE_FORMAT` (função exclusiva do MySQL) em
`calculateHomeExpenses()` (linha 129) e `calculateCreditCardData()`
(linha 154) — código de "despesas da casa", que este refactor deixou fora de
escopo. Trocar esses dois `whereRaw` por `whereYear`/`whereMonth` tornaria
todo o resumo mensal testável; fica como próxima etapa sugerida.

## Objetivo
Fazer os serviços legados (`app/Services/MonthlyBalanceCalculator.php` e
`app/Services/MonthlySummaryService.php`) consumirem o novo domínio em vez de
SQL cru, sem quebrar quem os consome hoje (`MonthlySummaryService`,
`ComputedResults.php`, `CalculateMonthlySummary` command).

## `app/Services/MonthlyBalanceCalculator.php`

Reescrever para:
```php
<?php

namespace App\Services;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\Services\CalculadoraDeSaldoMensal;

class MonthlyBalanceCalculator
{
    public function __construct(
        private readonly RepositorioDeLancamentos $repositorio,
        private readonly CalculadoraDeSaldoMensal $calculadora = new CalculadoraDeSaldoMensal(),
    ) {}

    /**
     * Mantém o formato de retorno legado (list de arrays com month_year,
     * participant, total_paid_common, share_common, balance) para não
     * quebrar app/Filament/Pages/ComputedResults.php, que consome esse
     * shape hoje.
     */
    public function calculateFinalBalances(): array
    {
        $participantes = Participant::cases();
        $resultado = [];

        foreach ($this->repositorio->competenciasDisponiveis() as $mes) {
            $lancamentos = $this->repositorio->paraCompetencia($mes);
            $saldoMensal = $this->calculadora->calcular($mes, $lancamentos, $participantes);

            // total_paid_common e share_common não existem mais como
            // conceitos separados no novo MonthlyBalance — recalcular aqui
            // a partir dos mesmos lançamentos para preencher o array legado
            // (ver nota abaixo).
            [$totalPagoPorParticipante, $cotaPorParticipante] = $this->resumoComumPorParticipante($lancamentos, $mes, $participantes);

            foreach ($participantes as $participante) {
                $resultado[] = [
                    'month_year' => $mes->chave(),
                    'participant' => $participante->value,
                    'total_paid_common' => $totalPagoPorParticipante[$participante->value],
                    'share_common' => $cotaPorParticipante[$participante->value],
                    'balance' => $saldoMensal->saldoDe($participante)->paraDecimal(),
                ];
            }
        }

        return $resultado;
    }

    // ... método auxiliar privado resumoComumPorParticipante() replicando
    // só a parte de soma/cota (sem dívida cruzada) para preencher os campos
    // de relatório que o array legado expõe.
}
```

**Nota importante:** o array legado hoje expõe `total_paid_common` e
`share_common` como campos informativos (usados só para exibição, não para
lógica). Ao portar, avaliar se vale a pena manter esses campos ou se
`ComputedResults.php` pode ser simplificado para não depender deles — **mas
isso é opcional**; o critério mínimo desta task é não quebrar
`ComputedResults.php`. Se for mais simples, expor esses dois campos
diretamente do `CalculadoraDeSaldoMensal` (ex.: fazer `MonthlyBalance`
carregar também `totalPagoDe(Participant)` e `cotaDe(Participant)`) em vez de
recalcular fora — decisão de implementação, desde que o array de saída
mantenha as mesmas chaves e valores equivalentes aos de hoje.

## `app/Services/MonthlySummaryService.php`

Em `calculateBalances(string $month): array` (privado, chamado por
`calculateAndPersistSummary()`):
- Trocar a lógica de "achar o primeiro saldo negativo no array bruto" por
  uso direto do domínio:
  ```php
  private function calculateBalances(string $month): array
  {
      $mes = ReferenceMonth::fromString($month);
      $saldoMensal = $this->calculadora->calcular(
          $mes,
          $this->repositorio->paraCompetencia($mes),
          Participant::cases(),
      );

      $acertos = $saldoMensal->acertos();

      if ($acertos === []) {
          return ['difference' => 0, 'payer' => null];
      }

      $acerto = $acertos[0];

      return [
          'difference' => $acerto->valor->paraDecimal(), // ou centavos, conforme o resto da classe espera
          'payer' => $acerto->paga->value, // confirmar: "payer" no código legado = quem deve pagar a diferença (saldo negativo)
      ];
  }
  ```
  Injetar `RepositorioDeLancamentos` e `CalculadoraDeSaldoMensal` no
  construtor de `MonthlySummaryService` (via `__construct`, Laravel resolve
  automaticamente pelo binding da Task 08).
- **Atenção ao sinal**: no código legado, `payer` é quem tem saldo negativo
  (`$debtor = $monthBalances->where('balance', '<', 0)->first()`) e
  `difference` é esse saldo negativo (`$debtor['balance']`, portanto
  negativo). Ao portar para `Acerto` (onde `paga` é quem deve e `valor` é
  sempre positivo), decidir se `calculateAndPersistSummary()` espera valor
  negativo ou positivo em `balance_difference`/`balance_payer` — hoje usa
  `abs($balances['difference'])` na linha 64, então valor absoluto já é
  esperado ali; ajustar o retorno de `calculateBalances()` para não quebrar
  esse `abs()` (ou remover o `abs()` redundante se `calculateBalances()` já
  devolver valor positivo — escolher uma abordagem e manter consistente).

## Testes
Não é necessário criar teste novo específico para estes dois arquivos nesta
task (eles são orquestração fina sobre o que já foi testado no domínio), mas
rodar a suíte completa e, se existir, o teste de feature de
`MonthlySummaryService`/`CalculateMonthlySummary` (checar se `tests/` tem
algum antes de assumir que não existe).

## Critério de conclusão
- `php artisan test` completo passa.
- `php artisan monthly:calculate <mês>` roda sem erro contra o banco local.
- `ComputedResults.php` (página Filament) continua renderizando sem erro
  (verificação manual no navegador, já que não há teste automatizado dela).
