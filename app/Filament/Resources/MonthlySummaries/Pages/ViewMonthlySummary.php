<?php

declare(strict_types=1);

namespace App\Filament\Resources\MonthlySummaries\Pages;

use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use App\Models\MonthlySummary;
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
}
