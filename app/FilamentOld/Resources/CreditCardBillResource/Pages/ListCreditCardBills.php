<?php

namespace App\Filament\Resources\CreditCardBillResource\Pages;

use App\Filament\Resources\CreditCardBillResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCreditCardBills extends ListRecords
{
    protected static string $resource = CreditCardBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
