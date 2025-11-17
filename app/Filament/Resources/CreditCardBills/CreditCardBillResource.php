<?php

namespace App\Filament\Resources\CreditCardBills;

use App\Filament\Resources\CreditCardBills\Pages\CreateCreditCardBill;
use App\Filament\Resources\CreditCardBills\Pages\EditCreditCardBill;
use App\Filament\Resources\CreditCardBills\Pages\ListCreditCardBills;
use App\Filament\Resources\CreditCardBills\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\CreditCardBills\Schemas\CreditCardBillForm;
use App\Filament\Resources\CreditCardBills\Tables\CreditCardBillsTable;
use App\Models\CreditCardBill;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CreditCardBillResource extends Resource
{
    protected static ?string $model = CreditCardBill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Fatura de cartão';
    protected static ?string $pluralModelLabel = 'Faturas de cartão';
    protected static ?int $navigationSort = 1;


    public static function form(Schema $schema): Schema
    {
        return CreditCardBillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditCardBillsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditCardBills::route('/'),
            'create' => CreateCreditCardBill::route('/create'),
            'edit' => EditCreditCardBill::route('/{record}/edit'),
        ];
    }
}
