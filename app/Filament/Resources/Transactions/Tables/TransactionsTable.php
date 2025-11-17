<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('creditCardBill.title_description_owner')->label('Fatura'),
                TextColumn::make('creditCardBill.owner_bill')->label('Dono'),
                TextColumn::make('transaction_date')->label('Data')->dateTime('d/m/Y'),
                TextColumn::make('description')->label('Descrição')
                    ->suffix(fn(Transaction $record): ?string => $record->origin ? ': ' . $record->origin : ''),
                TextColumn::make('parcelas')->label('Parcelas'),
                TextColumn::make('amount')->money('BRL')->label('Valor'),
                TextColumn::make('who_paid')->label('Pagador'),
                TextColumn::make('responsible_for_expense')->label('Devedor'),
                IconColumn::make('individual_expense')->label('Individual'),
                IconColumn::make('common_expense')
                    ->label('Em comum')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
            ])
            ->paginated([25])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
