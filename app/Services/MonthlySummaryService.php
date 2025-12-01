<?php

namespace App\Services;

use App\Models\CreditCardBill;
use App\Models\MonthlySummary;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MonthlySummaryService
{

    private MonthlyBalanceCalculator $balanceCalculator;

    public function __construct(MonthlyBalanceCalculator $balanceCalculator)
    {
        $this->balanceCalculator = $balanceCalculator;
    }

    /**
     * Calcula e persiste o resumo mensal para um mês específico
     */
    public function calculateAndPersistSummary(string $referenceMonth): MonthlySummary
    {
        // Validar formato do mês (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $referenceMonth)) {
            throw new InvalidArgumentException('Formato de mês inválido. Use YYYY-MM');
        }

        // Calcular mês de vencimento (referência + 1 mês)
        $duePaymentMonth = Carbon::createFromFormat('Y-m', $referenceMonth)
            ->addMonth()
            ->format('Y-m');

        // Buscar ou criar registro
        $summary = MonthlySummary::query()->firstOrNew(['reference_month' => $referenceMonth.'-01']);

        // Calcular despesas da casa
        $homeExpenses = $this->calculateHomeExpenses($duePaymentMonth);

        // Calcular dados dos cartões de crédito
        $creditCardData = $this->calculateCreditCardData($duePaymentMonth);

        // Calcular saldos finais
        $balances = $this->calculateBalances($duePaymentMonth);

        // Preencher dados
        $summary->fill([
            'due_payment_month' => $duePaymentMonth.'-01',

            // Despesas da casa
            'house_rental' => $homeExpenses['aluguel'] ?? 0,
            'condominium' => $homeExpenses['condominio'] ?? 0,
            'eventual_apartment' => $homeExpenses['eventualidades'] ?? 0,
            'electricity_bill' => $homeExpenses['light'] ?? 0,
            'gas_bill' => $homeExpenses['naturgy'] ?? 0,
            'internet_bill' => $homeExpenses['claro'] ?? 0,
            'total_home_expenses' => $homeExpenses['total'],
            'total_home_expenses_per_person' => $homeExpenses['total'] / 2,

            // Saldos
            'balance_difference' => abs($balances['difference']),
            'balance_payer' => $balances['payer'],

            // Dados D
            'd_credit_card_total' => $creditCardData['D']['total'] ?? 0,
            'd_credit_card_common' => $creditCardData['D']['common'] ?? 0,
            'd_credit_card_individual' => $creditCardData['D']['individual'] ?? 0,
            'd_living_cost' => $this->calculateLivingCost('D', $homeExpenses['total'], $creditCardData),

            // Dados J
            'j_credit_card_total' => $creditCardData['J']['total'] ?? 0,
            'j_credit_card_common' => $creditCardData['J']['common'] ?? 0,
            'j_credit_card_individual' => $creditCardData['J']['individual'] ?? 0,
            'j_living_cost' => $this->calculateLivingCost('J', $homeExpenses['total'], $creditCardData),

            // Metadados
            'is_calculated' => true,
            'calculated_at' => now(),
            'calculation_notes' => "Calculado automaticamente em " . now()->format('d/m/Y H:i:s'),
        ]);

        $summary->save();

        Log::info("Resumo mensal calculado para $referenceMonth", [
            'reference_month' => $referenceMonth,
            'total_home_expenses' => $homeExpenses['total'],
            'balance_payer' => $balances['payer'],
            'balance_difference' => $balances['difference']
        ]);

        return $summary;
    }

    /**
     * Recalcula múltiplos meses
     */
    /*public function recalculateMultipleMonths(array $months): array
    {
        $results = [];

        foreach ($months as $month) {
            try {
                $results[$month] = $this->calculateAndPersistSummary($month);
            } catch (\Exception $e) {
                Log::error("Erro ao calcular mês {$month}: " . $e->getMessage());
                $results[$month] = null;
            }
        }

        return $results;
    }*/

    /**
     * Calcula despesas da casa para um mês
     */
    private function calculateHomeExpenses(string $month): array
    {
        $expenses = Transaction::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Aluguel' THEN amount END), 0) AS aluguel")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Condomínio' THEN amount END), 0) AS condominio")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Eventualidades' THEN amount END), 0) AS eventualidades")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'LIGHT' THEN amount END), 0) AS light")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Naturgy' THEN amount END), 0) AS naturgy")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Claro' THEN amount END), 0) AS claro")
            ->whereNull('credit_card_bill_id')
            ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month])
            ->first();

        if (!$expenses) {
            return [
                'aluguel' => 0, 'condominio' => 0, 'eventualidades' => 0,
                'light' => 0, 'naturgy' => 0, 'claro' => 0, 'total' => 0
            ];
        }

        $expensesArray = $expenses->toArray();
        $expensesArray['total'] = array_sum(array_values($expensesArray));

        return $expensesArray;
    }

    /**
     * Calcula dados dos cartões de crédito
     */
    private function calculateCreditCardData(string $month): array
    {
        $data = ['D' => [], 'J' => []];

        foreach (['D', 'J'] as $owner) {
            $bill = CreditCardBill::query()
                ->whereRaw("DATE_FORMAT(due_date, '%Y-%m') = ?", [$month])
                ->where('owner_bill', $owner)
                ->first();

            if ($bill) {
                $data[$owner] = [
                    'total' => $bill->amount,
                    'common' => $bill->common_amount,
                    'individual' => $bill->individual_amount,
                ];
            } else {
                $data[$owner] = [
                    'total' => 0,
                    'common' => 0,
                    'individual' => 0,
                ];
            }
        }

        return $data;
    }

    /**
     * Calcula saldos usando o MonthlyBalanceCalculator existente
     */
    private function calculateBalances(string $month): array
    {
        $finalBalances = $this->balanceCalculator->calculateFinalBalances();

        $monthBalances = collect($finalBalances)->where('month_year', $month);

        if ($monthBalances->isEmpty()) {
            return ['difference' => 0, 'payer' => null];
        }

        // Encontrar quem deve (saldo negativo)
        $debtor = $monthBalances->where('balance', '<', 0)->first();

        if (!$debtor) {
            return ['difference' => 0, 'payer' => null];
        }

        return [
            'difference' => $debtor['balance'],
            'payer' => $debtor['participant']
        ];
    }

    /**
     * Calcula custo de vida individual
     */
    private function calculateLivingCost(string $participant, float $totalHomeExpenses, array $creditCardData): float
    {
        $homeExpensesPerPerson = $totalHomeExpenses / 2;
        $individualCreditCard = $creditCardData[$participant]['individual'] ?? 0;

        // Somar gastos comuns dos dois cartões e dividir por 2
        $totalCommonCreditCard = ($creditCardData['D']['common'] ?? 0) + ($creditCardData['J']['common'] ?? 0);
        $commonCreditCardPerPerson = $totalCommonCreditCard / 2;

        return $homeExpensesPerPerson + $individualCreditCard + $commonCreditCardPerPerson;
    }

    /**
     * Busca resumo de um mês específico
     */
//    public function getSummaryByMonth(string $referenceMonth): ?MonthlySummary
//    {
//        return MonthlySummary::byReferenceMonth($referenceMonth)->first();
//    }

    /**
     * Lista últimos N meses calculados
     */
//    public function getRecentSummaries(int $limit = 12): \Illuminate\Database\Eloquent\Collection
//    {
//        return MonthlySummary::latest()->calculated()->limit($limit)->get();
//    }

}
