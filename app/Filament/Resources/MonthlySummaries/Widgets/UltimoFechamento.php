<?php

declare(strict_types=1);

namespace App\Filament\Resources\MonthlySummaries\Widgets;

use App\Models\MonthlySummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

/**
 * Resumo do fechamento mais recente, no topo do Consolidado Mensal.
 */
class UltimoFechamento extends StatsOverviewWidget
{
    /** Evento disparado pela listagem após calcular/recalcular. */
    public const string EVENTO_ATUALIZADO = 'fechamento-atualizado';

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = 4;

    protected function getStats(): array
    {
        $ultimo = MonthlySummary::query()
            ->orderByDesc('competencia')
            ->first();

        if ($ultimo === null) {
            return [];
        }

        return [
            Stat::make('Última competência', $ultimo->competencia->format('m/Y'))
                ->description('gastos de ' . $ultimo->reference_month->format('m/Y')),

            Stat::make('Despesas da casa', $this->moeda($ultimo->total_home_expenses)),

            Stat::make('Total comum', $this->moeda($ultimo->total_common_expenses)),

            Stat::make('Acerto', new HtmlString($ultimo->balance_description))
                ->description($ultimo->difference_paid ? 'já acertado' : 'em aberto')
                ->color($ultimo->settlement_payer === null ? 'success' : 'warning'),
        ];
    }

    /** Re-renderiza; os stats são lidos de novo do banco. */
    #[On(self::EVENTO_ATUALIZADO)]
    public function atualizar(): void {}

    private function moeda(string|float|int|null $valor): string
    {
        return (string) Number::currency((float) ($valor ?? 0), in: 'BRL', locale: 'pt_BR');
    }
}
