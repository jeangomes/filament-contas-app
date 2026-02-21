<?php

namespace App\Filament\Resources\CreditCardBills\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                    ->hiddenOn(Operation::Edit)
                    ->saved(false)
                    ->boolean(),
                Select::make('origin_format')->label('Origem das transações')->required()
                    ->hiddenOn(Operation::Edit)
                    ->saved(false)
                    ->live()
                    ->options([
                        'CSV' => 'CSV',
                        'PDF' => 'PDF',
                    ]),
                Textarea::make('content_transaction')->label('Transações do PDF ou Excel')
                    ->visibleOn('create')
                    ->saved(false)
                    ->visible(fn (Get $get): bool => $get('origin_format') === 'PDF')
                    ->required()
                    ->rows(10)
                    ->cols(20)->columnSpanFull(),
                FileUpload::make('csv_file')
                    ->label('Arquivo CSV')
                    ->visible(fn (Get $get): bool => $get('origin_format') === 'CSV')
                    ->disk('private')
                    ->directory('imports/csv')
                    ->acceptedFileTypes(['text/csv', 'text/plain'])
                    ->required(fn (Get $get): bool => $get('origin_format') === 'CSV')
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => (string) str($file->getClientOriginalName())
                            ->prepend('fatura-'),
                    )
                    ->rules(['file', 'mimes:csv,txt']),

            ])->columns(3);
    }
}
