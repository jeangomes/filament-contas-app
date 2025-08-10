<?php

namespace App\Filament\Resources\CreditCardBillResource\RelationManagers;

use App\Models\Transaction;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SimpleTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $modelLabel = 'transação';
    protected static ?string $title = 'Copiar Transações';

    public function canCreate(): bool
    {
       return false;
    }

    public function table(Table $table): Table
    {
        return $table->recordUrl(null)->recordAction(null)->heading('Selecione para copiar')
            ->recordClasses(fn (Transaction $record) => match ($record->individual_expense) {
                true => 'my-bg-primary',
                false => '',
            })
            ->emptyStateDescription('Depois que você salvar a primeira versão, ela aparecerá aqui.')
            ->recordTitleAttribute('description')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')->label('Data')->dateTime('d/m/Y'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->summarize(Count::make()),
                Tables\Columns\TextColumn::make('amount')->label('Valor') ->numeric(),
                Tables\Columns\TextColumn::make('individual_expense')
                    ->state(function (Transaction $record) {
                        return $record->individual_expense ? 'S' : 'N';
                    })
                    ->label('Gasto individual'),
            ])
            ->defaultSort(fn ($query) => $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc'))
            ->filters([
                //
            ]);
    }
}
