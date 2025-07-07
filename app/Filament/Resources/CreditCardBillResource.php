<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditCardBillResource\Pages;
use App\Filament\Resources\CreditCardBillResource\RelationManagers;
use App\Models\CreditCardBill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\ColumnGroup;

class CreditCardBillResource extends Resource
{
    protected static ?string $model = CreditCardBill::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Fatura de cartão';
    protected static ?string $pluralModelLabel = 'Faturas de cartão';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title_description_owner')->label('Descrição')
                    ->required()->default('Fatura NB')
                    ->maxLength(255),
                Forms\Components\Select::make('owner_bill')->label('Dono/Pagador da fatura')->required()
                    ->options([
                        'D' => 'D',
                        'J' => 'J',
                    ]),
                Forms\Components\TextInput::make('amount')->label('Valor')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required(),
                Forms\Components\DatePicker::make('due_date')->label('Data de vencimento')
                    ->required(),
                Forms\Components\Select::make('most_common_expenses')->label('Em comum por padrão')->required()
                    ->boolean(),
                Forms\Components\Select::make('origin_format')->label('Origem das transações')->required()
                    ->options([
                        'CSV' => 'CSV',
                        'PDF' => 'PDF',
                    ]),
                Forms\Components\Textarea::make('content_transaction')->label('Transações')
                    ->visibleOn('create')
                    ->required()
                    ->rows(10)
                    ->cols(20)->columnSpanFull(),


            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_date_computed')
                    ->label('Referência')
                    ->html()
                    ->state(function (CreditCardBill $record) {
                        $owner = $record->owner_bill === 'D' ? 'Denilson' : 'Jean';
                        $yearBill = $record->due_date->format('Y');
                        $monthBill = $record->due_date->getTranslatedMonthName();
                        $sub = $record->due_date->subMonth();
                        $previousMonthName = $sub->getTranslatedMonthName();
                        $yearExpenses = $sub->format('Y');
                        return "<strong>".$record->title_description_owner . ": $owner</strong>" .
                            "<br>Referente a $previousMonthName/$yearExpenses <br> Pgto $monthBill/$yearBill";
                    }),
                Tables\Columns\TextColumn::make('due_date_format1')
                    ->label('Mês/Ano Ref')
                    ->state(function (CreditCardBill $record) {
                        return $record->due_date->subMonth();
                    })
                    ->dateTime('m/Y'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->dateTime('d/m/Y'),
                Tables\Columns\TextColumn::make('transactions_count')->counts('transactions')
                    ->label('Transações')->toggleable(isToggledHiddenByDefault: true),

                ColumnGroup::make('Valores', [
                    Tables\Columns\TextColumn::make('amount')->money('BRL')->label('Total Fatura'),
                    Tables\Columns\TextColumn::make('individual_amount')/*->sum([
                        'transactions' => fn(Builder $query) => $query->where('individual_expense', 1),
                    ], 'amount')*/->money('BRL')->label('Individual'),

                    Tables\Columns\TextColumn::make('common_amount')/*->sum([
                        'transactions as transactions_common' => fn(Builder $query) => $query->where('common_expense', '=', 1)
                    ], 'amount')*/->money('BRL')->label(new HtmlString('Total <br /> Comum')),

                    Tables\Columns\TextColumn::make('common_amount_divided_by_two')
                        ->state(function (CreditCardBill $record) {
                            return $record->common_amount / 2;
                        })
                        ->money('BRL')->label(new HtmlString('Divisão <br /> por 2')),
                ])
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->hiddenLabel(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditCardBills::route('/'),
            'create' => Pages\CreateCreditCardBill::route('/create'),
            'edit' => Pages\EditCreditCardBill::route('/{record}/edit'),
        ];
    }
}
