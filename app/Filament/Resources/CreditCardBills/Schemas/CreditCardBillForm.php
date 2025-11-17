<?php

namespace App\Filament\Resources\CreditCardBills\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CreditCardBillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title_description_owner')->label('Descrição')
                    ->required()->default('Fatura NB')
                    ->maxLength(255),
                Select::make('owner_bill')->label('Dono/Pagador da fatura')->required()
                    ->options([
                        'D' => 'D',
                        'J' => 'J',
                    ]),
                TextInput::make('amount')->label('Valor')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required(),
                DatePicker::make('due_date')->label('Data de vencimento')
                    ->required(),
                Select::make('most_common_expenses')->label('Em comum por padrão')->required()
                    ->boolean(),
                Select::make('origin_format')->label('Origem das transações')->required()
                    ->options([
                        'CSV' => 'CSV',
                        'PDF' => 'PDF',
                    ]),
                Textarea::make('content_transaction')->label('Transações')
                    ->visibleOn('create')
                    ->required()
                    ->rows(10)
                    ->cols(20)->columnSpanFull(),
            ])->columns(3);
    }
}
