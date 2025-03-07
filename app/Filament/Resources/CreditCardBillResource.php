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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\ColumnGroup;

class CreditCardBillResource extends Resource
{
    protected static ?string $model = CreditCardBill::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Fatura de cartão/Conta';
    protected static ?string $pluralModelLabel = 'Faturas de cartão/Contas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title_description_owner')->label('Descrição/Dono da fatura')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('owner_bill')->label('Dono/Pagador da fatura')
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
                Forms\Components\TextInput::make('observation')->label('Obs')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('content_transaction')->label('Transações')
                    ->visibleOn('create')
                    ->required()
                    ->rows(10)
                    ->cols(20),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_description_owner')->label('Fatura'),
                Tables\Columns\TextColumn::make('owner_bill')->label('De quem?'),
                Tables\Columns\TextColumn::make('reference_date_computed')
                    ->label('Data de referência')
                    ->html()
                    ->state(function (CreditCardBill $record) {
                        $yearBill = $record->due_date->format('Y');
                        $monthBill = $record->due_date->getTranslatedMonthName();
                        $sub = $record->due_date->subMonth();
                        $previousMonthName = $sub->getTranslatedMonthName();
                        $yearExpenses = $sub->format('Y');
                        return "Referente a {$previousMonthName}/{$yearExpenses} <br> Pgto {$monthBill}/{$yearBill}";
                    }),
                Tables\Columns\TextColumn::make('due_date_format1')
                    ->label('Mês/Ano Ref')
                    ->state(function (CreditCardBill $record) {
                        $sub = $record->due_date->subMonth();
                        return $sub;
                    })
                    ->dateTime('m/Y'),
                    //->timezone('America/Sao_Paulo'),
                /*Tables\Columns\TextColumn::make('due_date_format2')
                    ->label('Mês/Ano Vcto')
                    ->state(function (CreditCardBill $record) {
                        return $record->due_date->format('m/Y');
                    }),*/
                    //->timezone('America/Sao_Paulo'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->dateTime('d/m/Y'),
                    //->timezone('America/Sao_Paulo'),
                //Tables\Columns\TextColumn::make('observation'),
                Tables\Columns\TextColumn::make('amount')->money('BRL')->label('Valor'),
               ColumnGroup::make('Valores', [

                   Tables\Columns\TextColumn::make('transactions_sum_amount')->sum([
                       'transactions' => fn (Builder $query) => $query->where('individual_expense', 1),
                   ], 'amount')->money('BRL')->label('Individual'),

                   Tables\Columns\TextColumn::make('transactions_common')->sum([
                       'transactions as transactions_common' => fn (Builder $query) => $query->where('common_expense','=', 1)
                   ], 'amount')->money('BRL') ->label(new HtmlString('Em <br /> Comum')),

                   Tables\Columns\TextColumn::make('common_amount_divided_by_two')
                       ->state(function (CreditCardBill $record) {
                        return $record->transactions_common / 2;
                        })
                       ->money('BRL')->label(new HtmlString('Comum <br /> Dividido')),
                ]),
                Tables\Columns\TextColumn::make('transactions_count')->counts('transactions')->label('Transações'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
