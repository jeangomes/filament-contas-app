<?php

namespace App\Filament\Resources\CreditCardBills\Pages;

use App\Filament\Resources\CreditCardBills\CreditCardBillResource;
use App\Filament\Resources\CreditCardBills\Widgets\TransactionsByCategory;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCreditCardBill extends EditRecord
{
    protected static string $resource = CreditCardBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TransactionsByCategory::class,
        ];
    }
}
