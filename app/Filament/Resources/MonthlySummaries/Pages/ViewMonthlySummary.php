<?php

declare(strict_types=1);

namespace App\Filament\Resources\MonthlySummaries\Pages;

use App\Filament\Pages\RelatorioDaCompetencia;
use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use App\Models\MonthlySummary;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewMonthlySummary extends ViewRecord
{
    protected static string $resource = MonthlySummaryResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var MonthlySummary $registro */
        $registro = $this->getRecord();

        return 'Fechamento de ' . $registro->competencia->format('m/Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('relatorio')
                ->label('Relatório detalhado')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->url(function (): string {
                    /** @var MonthlySummary $registro */
                    $registro = $this->getRecord();

                    return RelatorioDaCompetencia::getUrl(['mes' => $registro->competencia->format('Y-m')]);
                }),
        ];
    }
}
