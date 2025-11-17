<?php

namespace App\Filament\Resources\CreditCardBills\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use App\Models\CreditCardBill;

class CreditCardBillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_date_computed')
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
                TextColumn::make('due_date_format1')
                    ->label('Mês/Ano Ref')
                    ->state(function (CreditCardBill $record) {
                        return $record->due_date->subMonth();
                    })
                    ->dateTime('m/Y'),
                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->dateTime('d/m/Y'),
                TextColumn::make('transactions_count')->counts('transactions')
                    ->label('Transações')->toggleable(isToggledHiddenByDefault: true),

                ColumnGroup::make('Valores', [
                    TextColumn::make('amount')->money('BRL')->label('Total Fatura'),
                    TextColumn::make('individual_amount')/*->sum([
                        'transactions' => fn(Builder $query) => $query->where('individual_expense', 1),
                    ], 'amount')*/->money('BRL')->label('Individual'),

                    TextColumn::make('common_amount')/*->sum([
                        'transactions as transactions_common' => fn(Builder $query) => $query->where('common_expense', '=', 1)
                    ], 'amount')*/->money('BRL')->label(new HtmlString('Total <br /> Comum')),

                    TextColumn::make('common_amount_divided_by_two')
                        ->state(function (CreditCardBill $record) {
                            return $record->common_amount / 2;
                        })
                        ->money('BRL')->label(new HtmlString('Divisão <br /> por 2')),
                ])
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
