<?php

namespace App\Livewire;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ListResults extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function table(Table $table): Table
    {
        return $table
            ->query(Transaction::query())
            ->paginated(false)
            ->columns([
                TextColumn::make('mes_pagamento')
                    ->label('Mês de Pagamento'),
                TextColumn::make('aluguel'),
                TextColumn::make('condominio'),
                TextColumn::make('eventualidades'),
                TextColumn::make('light'),
                TextColumn::make('naturgy'),
                TextColumn::make('claro'),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                // ...
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function render()
    {
        $resultados = DB::select("
            SELECT
                DATE_FORMAT(t.transaction_date, '%m/%Y') AS mes_pagamento,
                CONCAT('R$ ', FORMAT(COALESCE(SUM(CASE WHEN t.description = 'Aluguel' THEN t.amount END), 0), 2, 'pt_BR')) AS aluguel,
                CONCAT('R$ ', FORMAT(COALESCE(SUM(CASE WHEN t.description = 'Condominio' THEN t.amount END), 0), 2, 'pt_BR')) AS condominio,
                CONCAT('R$ ', FORMAT(COALESCE(SUM(CASE WHEN t.description = 'Eventualidades' THEN t.amount END), 0), 2, 'pt_BR')) AS eventualidades,
                CONCAT('R$ ', FORMAT(COALESCE(SUM(CASE WHEN t.description = 'LIGHT' THEN t.amount END), 0), 2, 'pt_BR')) AS light,
                CONCAT('R$ ', FORMAT(COALESCE(SUM(CASE WHEN t.description = 'Naturgy' THEN t.amount END), 0), 2, 'pt_BR')) AS naturgy,
                CONCAT('R$ ', FORMAT(COALESCE(SUM(CASE WHEN t.description = 'Claro' THEN t.amount END), 0), 2, 'pt_BR')) AS claro
            FROM transactions t
            WHERE t.credit_card_bill_id IS NULL
            GROUP BY mes_pagamento;
        ");


        //dd($resultados);
        return view('livewire.list-results', [
            'resultados' => $resultados,
            'finalBalances' => $this->calculationBalance()
        ]);
    }

    private function calculationBalance(): array
    {
        $totalPaidCommon = DB::select("SELECT who_paid AS participant, SUM(transactions.amount) AS total_paid, DATE_FORMAT(ccb.due_date, '%Y-%m') AS mes_ano
        FROM transactions inner join credit_card_bills ccb on transactions.credit_card_bill_id = ccb.id
        WHERE common_expense = 1
        GROUP BY mes_ano, who_paid
        order by mes_ano,participant");

        $balancesByMonth = [];

        // Passo 2: Processar os dados brutos
        foreach ($totalPaidCommon as $entry) {
            $mesAno = $entry->mes_ano;  // Obtém o mês e ano da transação
            $participant = $entry->participant;  // Quem pagou
            $totalPaid = $entry->total_paid;  // Quanto pagou

            if (!isset($balancesByMonth[$mesAno])) {
                $balancesByMonth[$mesAno] = [
                    'total_common' => 0, // Total de despesas comuns do mês
                    'participants' => [], // Lista de participantes e quanto pagaram
                ];
            }

            // Soma os valores pagos por todos os participantes no mês
            $balancesByMonth[$mesAno]['total_common'] += $totalPaid;

            // Armazena quanto cada participante pagou
            $balancesByMonth[$mesAno]['participants'][$participant] = $totalPaid;
        }

        // Passo 3: Calcular a divisão dos gastos

        $finalBalances = [];

        foreach ($balancesByMonth as $mesAno => $data) {
            $totalCommon = $data['total_common']; // Total de gastos comuns do mês
            $participants = count($data['participants']); // Quantidade de participantes
            $sharePerParticipant = $totalCommon / $participants; // Quanto cada um deveria pagar

            foreach ($data['participants'] as $participant => $totalPaid) {
                $balance = $totalPaid - $sharePerParticipant; // Diferença entre o que pagou e o que deveria pagar

                $finalBalances[] = [
                    'mes_ano' => $mesAno,
                    'participant' => $participant,
                    'total_paid' => $totalPaid,
                    'share' => $sharePerParticipant,
                    'balance' => $balance,
                ];
            }
        }
        return $finalBalances;
    }
}
