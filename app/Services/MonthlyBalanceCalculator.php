<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyBalanceCalculator
{

    // Define os participantes fixos que compartilham despesas comuns
    // Considerar tornar isso configurável (ex: via um .env ou tabela de configuração)
    const COMMON_EXPENSE_PARTICIPANTS = ['D', 'J'];

    /**
     * Calcula os saldos mensais para despesas comuns e individuais.
     *
     * @return array
     */
    public function calculateFinalBalances(): array
    {
        // 1. Obter despesas comuns pagas por cada participante no mês
        $totalPaidCommon = $this->getCommonExpensesPaidByParticipants();

        // 2. Processar os dados brutos de despesas comuns
        $balancesByMonth = $this->processCommonExpenseData($totalPaidCommon);

        // 3. Calcular a divisão dos gastos comuns
        $finalBalances = $this->calculateCommonExpenseShare($balancesByMonth);

        // 4. Incorporar despesas individuais pagas por outra pessoa (se o campo 'responsible_for_expense' existir)
        //$finalBalances = $this->incorporateIndividualExpensesPaidByOthers($finalBalances);

        return $finalBalances;
    }

    /**
     * Busca o total de despesas comuns pagas por cada participante por mês.
     *
     * @return Collection
     */
    private function getCommonExpensesPaidByParticipants(): Collection
    {
        // Usar DB::raw() para as funções de data diretamente no SELECT
        $dateVcto = "IF(credit_card_bill_id IS NOT NULL, DATE_FORMAT(ccb.due_date, '%Y-%m'), DATE_FORMAT(transaction_date, '%Y-%m'))";

        return DB::table('transactions')
            ->selectRaw('who_paid AS participant')
            ->selectRaw('SUM(transactions.amount) AS total_paid')
            ->selectRaw("$dateVcto AS month_year")
            ->leftJoin('credit_card_bills as ccb', 'transactions.credit_card_bill_id', '=', 'ccb.id')
            ->where('common_expense', true)
            ->where('type', '!=', 'pgto_de_fatura')
            ->groupByRaw('month_year, who_paid')
            ->orderByRaw('month_year, participant')
            ->get();
    }

    /**
     * Processa os dados brutos de despesas comuns para organizar por mês e participante.
     *
     * @param Collection $totalPaidCommon
     * @return array
     */
    private function processCommonExpenseData(Collection $totalPaidCommon): array
    {
        $balancesByMonth = [];
        foreach ($totalPaidCommon as $entry) {
            $monthYear = $entry->month_year;
            $participant = $entry->participant;
            $totalPaid = $entry->total_paid;

            if (!isset($balancesByMonth[$monthYear])) {
                $balancesByMonth[$monthYear] = [
                    'total_common' => 0,
                    'participants_paid_in_month' => [],
                ];
            }

            $balancesByMonth[$monthYear]['total_common'] += $totalPaid;
            $balancesByMonth[$monthYear]['participants_paid_in_month'][$participant] = $totalPaid;
        }
        return $balancesByMonth;
    }

    /**
     * Calcula a divisão dos gastos comuns e o saldo inicial para cada participante.
     *
     * @param array $balancesByMonth
     * @return array
     */
    private function calculateCommonExpenseShare(array $balancesByMonth): array
    {
        $finalBalances = [];
        $numberOfCommonParticipants = count(self::COMMON_EXPENSE_PARTICIPANTS);

        foreach ($balancesByMonth as $monthYear => $data) {
            $totalCommon = $data['total_common'];
            $sharePerParticipant = $totalCommon / $numberOfCommonParticipants;

            foreach (self::COMMON_EXPENSE_PARTICIPANTS as $participant) {
                $totalPaid = $data['participants_paid_in_month'][$participant] ?? 0;

                $balance = $totalPaid - $sharePerParticipant;

                $finalBalances[] = [
                    'month_year' => $monthYear, // Renomeado mes_ano para month_year
                    'participant' => $participant,
                    'total_paid_common' => $totalPaid, // Mais específico
                    'share_common' => $sharePerParticipant, // Mais específico
                    'balance' => $balance,
                ];
            }
        }
        return $finalBalances;
    }

    /**
     * Incorpora dívidas de despesas individuais pagas por outros.
     * Este método assume a existência do campo `responsible_for_expense` na tabela `transactions`.
     *
     * @param array $currentBalances
     * @return array
     */
    private function incorporateIndividualExpensesPaidByOthers(array $currentBalances): array
    {
        // Verifica se a coluna 'responsible_for_expense' existe antes de tentar usá-la
        if (!DB::getSchemaBuilder()->hasColumn('transactions', 'responsible_for_expense')) {
            return $currentBalances; // Se não existe, retorna os balanços atuais sem alterações
        }

        $individualDebts = DB::table('transactions')
            ->selectRaw('who_paid AS payer')
            ->selectRaw('responsible_for_expense AS beneficiary')
            ->selectRaw('SUM(amount) AS total_amount')
            ->selectRaw("IF(credit_card_bill_id IS NOT NULL, DATE_FORMAT(ccb.due_date, '%Y-%m'), DATE_FORMAT(transaction_date, '%Y-%m')) AS month_year")
            ->leftJoin('credit_card_bills as ccb', 'transactions.credit_card_bill_id', '=', 'ccb.id')
            ->where('common_expense', false) // Despesa individual
            ->whereColumn('who_paid', '!=', 'responsible_for_expense') // Pago por quem não é o responsável
            ->where('type', '!=', 'pgto_de_fatura')
            ->groupByRaw('month_year, payer, beneficiary')
            ->get();

        $updatedBalances = collect($currentBalances);

        foreach ($individualDebts as $debt) {
            $monthYear = $debt->month_year;
            $payer = $debt->payer;
            $beneficiary = $debt->beneficiary;
            $amount = $debt->total_amount;

            // Ajustar o saldo do pagador (quem pagou)
            $updatedBalances = $updatedBalances->map(function ($item) use ($monthYear, $payer, $amount) {
                if ($item['month_year'] === $monthYear && $item['participant'] === $payer) {
                    $item['balance'] += $amount; // Aumenta o crédito do pagador
                    $item['total_paid_common'] += $amount; // Pode ser útil para relatórios, ajustando o "total pago"
                }
                return $item;
            });

            // Ajustar o saldo do beneficiário (quem deve)
            $updatedBalances = $updatedBalances->map(function ($item) use ($monthYear, $beneficiary, $amount) {
                if ($item['month_year'] === $monthYear && $item['participant'] === $beneficiary) {
                    $item['balance'] -= $amount; // Diminui o crédito (aumenta o débito) do beneficiário
                }
                return $item;
            });
        }

        return $updatedBalances->toArray();
    }


}
