<?php

namespace App\Filament\Pages;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use Exception;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use BackedEnum;
use UnitEnum;

class LivingCost extends Page
{
    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.living-cost';
    protected static ?string $title = 'Custo de Vida';
    protected static string | UnitEnum | null $navigationGroup = 'Resultados/Relatórios';
    protected static ?int $navigationSort = 5;

    /**
     * @var Collection<int, Transaction>
     */
    public Collection $resultados;

    public function formatNumber(float|string|null $value): false|string
    {
        return $value !== null ? Number::currency($value, in: 'BRL', locale: 'pt_BR') : '';
    }

    public function mount(): void
    {
        //dd('laravel query n+1');
        $dateRef = "DATE_FORMAT(DATE_SUB(transaction_date, INTERVAL 1 MONTH), '%Y-%m')";
        $dateVcto = "DATE_FORMAT(transaction_date, '%Y-%m')";
        $this->resultados = Transaction::query()
            ->whereNull('credit_card_bill_id')
            ->selectRaw("MIN(id) as id")
            ->selectRaw("$dateRef AS mes_ref")
            ->selectRaw("$dateVcto AS mes_vcto")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Aluguel' THEN amount END), 0) AS aluguel")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Condomínio' THEN amount END), 0) AS condominio")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Eventualidades' THEN amount END), 0) AS eventualidades")//LIKE
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'LIGHT' THEN amount END), 0) AS light")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Naturgy' THEN amount END), 0) AS naturgy")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Claro' THEN amount END), 0) AS claro")
            ->selectRaw("COALESCE(SUM(CASE WHEN description = 'Outros' THEN amount END), 0) AS others")
            ->groupByRaw("$dateRef, $dateVcto")
            ->orderByRaw("$dateVcto desc")
            ->get();

        $this->resultados->map(function ($transaction) {
            $transaction->amount_home_expenses = array_sum([
                $transaction['aluguel'],
                $transaction['condominio'],
                $transaction['eventualidades'],
                $transaction['light'],
                $transaction['naturgy'],
                $transaction['claro'],
                $transaction['others']
            ]);
            $transaction->bill_d = $this->filterBalance('D', $transaction->mes_vcto);
            $transaction->bill_j = $this->filterBalance('J', $transaction->mes_vcto);
            //dd($transaction->bill_d);
            $transaction->bills = [
                'D' => 78,//rever
                'J' => 74
            ];
            $transaction->living_cost = [
                'D' => ($transaction->amount_home_expenses / 2) +
                    $transaction->bill_d?->individual_amount +
                    (($transaction->bill_d?->common_amount + $transaction->bill_j?->common_amount) / 2),
                'J' => ($transaction->amount_home_expenses / 2) +
                    $transaction->bill_j?->individual_amount +
                    (($transaction->bill_d?->common_amount + $transaction->bill_j?->common_amount) / 2)
            ];
            //dd($transaction);
            return $transaction;
        });
    }

    private function filterBalance($owner_bill, $dueMonth)
    {
        if (!$dueMonth) {
            return response()->json(['error' => 'year_month parameter is required'], 400);
        }

        try {
            $date = Carbon::createFromFormat('Y-m', $dueMonth);
        } catch (Exception) {
            return response()->json(['error' => 'Invalid year_month format. Use YYYY-MM'], 400);
        }
        $year = $date->year;
        $month = $date->month;

        return CreditCardBill::query()->whereYear('due_date', '=', $year)
            ->whereMonth('due_date', '=', $month)
            ->where('owner_bill', '=', $owner_bill)
            ->first();
    }
}
