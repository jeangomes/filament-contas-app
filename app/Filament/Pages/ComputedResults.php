<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Services\MonthlyBalanceCalculator;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class ComputedResults extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.computed-results';
    protected static ?string $title = 'Consolidado';
    protected static ?int $navigationSort = 3;

    public Collection $tableResults;
    public array $finalBalances;

    public function mount(): void
    {
        $calculator = new MonthlyBalanceCalculator();
        $finalBalances = $calculator->calculateFinalBalances();

        $dateRef = "DATE_FORMAT(DATE_SUB(transaction_date, INTERVAL 1 MONTH), '%Y-%m')";
        $dateVcto = "DATE_FORMAT(transaction_date, '%Y-%m')";
        $transactionsSummary = Transaction::query()
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

        // Mapear os resultados para incluir os balanços calculados e totais
        $this->tableResults = $transactionsSummary->map(function ($transactionSummary) use ($finalBalances) {
            $transactionSummary->amount_home_expenses = $transactionSummary->aluguel + $transactionSummary->condominio + $transactionSummary->eventualidades +
                $transactionSummary->light + $transactionSummary->naturgy + $transactionSummary->claro;

            // Encontrar o balanço final para este mês e identificar o devedor
            $balanceEntry = collect($finalBalances)->first(function ($value) use ($transactionSummary) {
                return $value['month_year'] === $transactionSummary->mes_vcto && $value['balance'] < 0;
            });

            $transactionSummary->balance = $balanceEntry ? $balanceEntry['balance'] : 0;
            $transactionSummary->balance_payer = $balanceEntry ? $balanceEntry['participant'] : ''; // Quem é o devedor

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


            return $transactionSummary;
        });

        $this->finalBalances = $finalBalances;
    }

    public function formatNumber($value): false|string
    {
        return Number::currency($value, in: 'BRL', locale: 'pt_BR');
    }
}
