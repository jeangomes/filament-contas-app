<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Services\MonthlyBalanceCalculator;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use BackedEnum;
use UnitEnum;
use Illuminate\Support\Number;

class ComputedResults extends Page
{
    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-credit-card';

    protected string $view = 'filament.pages.computed-results';
    protected static ?string $title = 'Consolidado (Cálculo dinâmico)';
    protected static string | UnitEnum | null $navigationGroup = 'Resultados/Relatórios';
    protected static ?int $navigationSort = 1;

    public Collection $tableResults;
    public array $finalBalances;

    public function mount(MonthlyBalanceCalculator $calculator): void
    {
        $rawBalances = $calculator->calculateFinalBalances();
        $balancesByMonth = collect($rawBalances)->groupBy('month_year');


        $dateRef = "DATE_FORMAT(DATE_SUB(transaction_date, INTERVAL 1 MONTH), '%Y-%m')";
        $dateVcto = "DATE_FORMAT(transaction_date, '%Y-%m')";
        $transactionsSummary = Transaction::query()
            ->selectRaw("MIN(id) as id")
            ->selectRaw("$dateRef AS mes_ref")
            ->selectRaw("$dateVcto AS mes_vcto")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Aluguel' THEN amount END), 0) AS aluguel")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Condomínio' THEN amount END), 0) AS condominio")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Eventualidades' THEN amount END), 0) AS eventualidades")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'LIGHT' THEN amount END), 0) AS light")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Naturgy' THEN amount END), 0) AS naturgy")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Claro' THEN amount END), 0) AS claro")
            ->whereNull('credit_card_bill_id')
            ->groupByRaw("$dateRef, $dateVcto")
            ->orderByRaw("$dateVcto desc")
            ->get();

        $this->tableResults = $transactionsSummary->map(function ($summary) use ($balancesByMonth) {
            // Cálculo do total de despesas da casa (Hardcoded na query acima)
            $summary->amount_home_expenses =
                $summary->aluguel +
                $summary->condominio +
                $summary->eventualidades +
                $summary->light +
                $summary->naturgy +
                $summary->claro;

            // Buscar dados do balanço para este mês específico
            /** @var Collection $monthBalances */
            $monthBalances = $balancesByMonth->get($summary->mes_vcto);

            // Inicializar valores padrão
            $summary->balance = 0;
            $summary->balance_payer = 'Ninguém'; // Ou string vazia

            if ($monthBalances) {
                // Encontrar quem ficou com saldo NEGATIVO (quem deve pagar a diferença)
                // Assumindo que o cálculo gera saldo negativo para quem pagou MENOS do que devia
                $debtor = $monthBalances->firstWhere('balance', '<', 0);

                if ($debtor) {
                    $summary->balance = $debtor['balance'];
                    $summary->balance_payer = $debtor['participant'];
                }
            }

            return $summary;
        });

        // Mapear os resultados para incluir os balanços calculados e totais
        /*$this->tableResults = $transactionsSummary->map(function ($transactionSummary) use ($finalBalances) {
            $transactionSummary->amount_home_expenses = $transactionSummary->aluguel + $transactionSummary->condominio + $transactionSummary->eventualidades +
                $transactionSummary->light + $transactionSummary->naturgy + $transactionSummary->claro;

            // Encontrar o balanço final para este mês e identificar o devedor
            $balanceEntry = collect($finalBalances)->first(function ($value) use ($transactionSummary) {
                return $value['month_year'] === $transactionSummary->mes_vcto && $value['balance'] < 0;
            });

            $transactionSummary->balance = $balanceEntry ? $balanceEntry['balance'] : 0;
            $transactionSummary->balance_payer = $balanceEntry ? $balanceEntry['participant'] : '';*/ // Quem é o devedor

            // Opcional: Adicionar o saldo do credor (quem tem o saldo positivo)
            /*$creditorEntry = collect($finalBalances)->first(function ($value) use ($transactionSummary) {
                return $value['month_year'] === $transactionSummary->mes_vcto && $value['balance'] > 0;
            });
            $transactionSummary->creditor_balance = $creditorEntry ? $creditorEntry['balance'] : 0;
            $transactionSummary->creditor_participant = $creditorEntry ? $creditorEntry['participant'] : '';*/

            // Opcional: Total de despesas comuns para o mês (para referência)
            /*$totalCommonForMonthEntry = collect($finalBalances)->first(function ($value) use ($transactionSummary) {
                return $value['month_year'] === $transactionSummary->mes_vcto;
            });
            $transactionSummary->total_common_expenses_calculated = $totalCommonForMonthEntry ? $totalCommonForMonthEntry['share_common'] * count(MonthlyBalanceCalculator::COMMON_EXPENSE_PARTICIPANTS) : 0;*/


            //return $transactionSummary;
        //});

        $this->finalBalances = $rawBalances;
    }

    public function formatNumber($value): false|string
    {
        return Number::currency($value, in: 'BRL', locale: 'pt_BR');
    }
}
