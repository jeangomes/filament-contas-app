<?php

namespace App\Filament\Resources\MonthlySummaries\Pages;

use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use Filament\Resources\Pages\ListRecords;

class ListMonthlySummaries extends ListRecords
{
    protected static string $resource = MonthlySummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
