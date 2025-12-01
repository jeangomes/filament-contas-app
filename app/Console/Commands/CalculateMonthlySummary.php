<?php

namespace App\Console\Commands;

use App\Services\MonthlySummaryService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateMonthlySummary extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monthly:calculate
                           {month? : Mês no formato YYYY-MM (default: mês anterior)}
                           {--all : Recalcular todos os últimos 12 meses}
                           {--from= : Calcular a partir de um mês específico}
                           {--to= : Calcular até um mês específico}';

    /**
     * The console command description.
     */
    protected $description = 'Calcula e persiste o resumo mensal de despesas';

    private MonthlySummaryService $summaryService;

    public function __construct(MonthlySummaryService $summaryService)
    {
        parent::__construct();
        $this->summaryService = $summaryService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
//            if ($this->option('all')) {
//                return $this->calculateAllMonths();
//            }
//
//            if ($this->option('from') && $this->option('to')) {
//                return $this->calculateRange();
//            }

            return $this->calculateSingleMonth();

        } catch (Exception $e) {
            $this->error("Erro durante o cálculo: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function calculateSingleMonth(): int
    {
        $month = $this->argument('month') ?? Carbon::now()->subMonth()->format('Y-m');

        $this->info("Calculando resumo para $month...");
        $summary = $this->summaryService->calculateAndPersistSummary($month);

        $this->newLine();
        $this->displaySummary($summary);

        $this->info("✅ Resumo calculado com sucesso!");

        return self::SUCCESS;
    }

    /*private function calculateAllMonths(): int
    {
        $this->info("Recalculando últimos 12 meses...");

        $months = collect();
        $currentMonth = Carbon::now()->subMonth();

        for ($i = 0; $i < 12; $i++) {
            $months->push($currentMonth->format('Y-m'));
            $currentMonth->subMonth();
        }

        $bar = $this->output->createProgressBar($months->count());
        $bar->start();

        $results = [];
        foreach ($months as $month) {
            try {
                $results[$month] = $this->summaryService->calculateAndPersistSummary($month);
                $bar->advance();
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Erro ao calcular {$month}: " . $e->getMessage());
                $results[$month] = null;
            }
        }

        $bar->finish();
        $this->newLine(2);

        $successful = collect($results)->filter()->count();
        $this->info("✅ {$successful} de {$months->count()} meses calculados com sucesso!");

        return self::SUCCESS;
    }*/

    /*private function calculateRange(): int
    {
        $from = Carbon::createFromFormat('Y-m', $this->option('from'));
        $to = Carbon::createFromFormat('Y-m', $this->option('to'));

        if ($from->gt($to)) {
            $this->error("A data 'from' deve ser anterior à data 'to'");
            return self::FAILURE;
        }

        $months = collect();
        $current = $from->copy();

        while ($current->lte($to)) {
            $months->push($current->format('Y-m'));
            $current->addMonth();
        }

        $this->info("Calculando {$months->count()} meses de {$from->format('Y-m')} até {$to->format('Y-m')}...");

        $bar = $this->output->createProgressBar($months->count());
        $bar->start();

        $results = [];
        foreach ($months as $month) {
            try {
                $results[$month] = $this->summaryService->calculateAndPersistSummary($month);
                $bar->advance();
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Erro ao calcular {$month}: " . $e->getMessage());
                $results[$month] = null;
            }
        }

        $bar->finish();
        $this->newLine(2);

        $successful = collect($results)->filter()->count();
        $this->info("✅ {$successful} de {$months->count()} meses calculados com sucesso!");

        return self::SUCCESS;
    }*/

    private function displaySummary($summary): void
    {
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Mês de Referência', $summary->reference_month],
                ['Mês de Vencimento', $summary->due_payment_month],
                ['Total Despesas Casa', 'R$ ' . number_format($summary->total_home_expenses, 2, ',', '.')],
                ['Despesas Casa por Pessoa', 'R$ ' . number_format($summary->total_home_expenses_per_person, 2, ',', '.')],
                ['Cartão D (Total)', 'R$ ' . number_format($summary->d_credit_card_total, 2, ',', '.')],
                ['Cartão J (Total)', 'R$ ' . number_format($summary->j_credit_card_total, 2, ',', '.')],
                ['Custo de Vida D', 'R$ ' . number_format($summary->d_living_cost, 2, ',', '.')],
                ['Custo de Vida J', 'R$ ' . number_format($summary->j_living_cost, 2, ',', '.')],
                ['Saldo', $summary->balance_description],
            ]
        );
    }
}
