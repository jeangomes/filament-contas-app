<?php

namespace App\Filament\Resources\CreditCardBills\Pages;

use App\Filament\Resources\CreditCardBills\CreditCardBillResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreditCardBills extends ListRecords
{
    protected static string $resource = CreditCardBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
