<?php

namespace App\Filament\Resources\MonthlySummaries\Pages;

use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMonthlySummary extends ViewRecord
{
    protected static string $resource = MonthlySummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
