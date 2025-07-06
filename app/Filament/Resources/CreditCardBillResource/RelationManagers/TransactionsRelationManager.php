<?php

namespace App\Filament\Resources\CreditCardBillResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\DB;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Filament\Tables\Columns\Summarizers\Summarizer;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $modelLabel = 'transação';
    protected static ?string $title = 'Transações';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')->label('Tipo')
                    ->required()
                    ->options([
                        'fixed_expense' => 'fixed_expense',
                        'pgto_de_fatura' => 'pgto_de_fatura',
                    ]),
                //TransactionResource::fieldDescription(),
                TransactionResource::fieldOrigin(),
                TransactionResource::fieldAmount(),
                TransactionResource::fieldTransactionDate(),
                TransactionResource::fieldWhoPaid(),
                TransactionResource::fieldCommon(),
                TransactionResource::fieldIndividual(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordUrl(null)->recordAction(null)
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
                Tables\Columns\TextColumn::make('parcelas'),
                Tables\Columns\TextColumn::make('amount')->money('BRL')->label('Valor')
                    ->summarize(Sum::make()->query(fn (QueryBuilder $query) => $query->where('type','!=', 'pgto_de_fatura'))),
                Tables\Columns\ToggleColumn::make('individual_expense')
                    ->label('Gasto Individual')
                    ->beforeStateUpdated(function ($record, $state) {
                        //dd($record, $state);
                        $record->common_expense = !$state;
                        // Runs before the state is saved to the database.
                    })
                    ->afterStateUpdated(function ($record, $state) {
                        // Runs after the state is saved to the database.
                    })
                    ->summarize(
                        Count::make()->query(fn (QueryBuilder $query) => $query->where('individual_expense', true)),
                    ),
                Tables\Columns\IconColumn::make('common_expense')
                    ->label('Gasto em comum')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->summarize([
                        Count::make()->query(fn (QueryBuilder $query) => $query->where('common_expense', true)),
                        Summarizer::make()
                            ->label('Total comum')
                            ->using(fn (QueryBuilder $query): string => $query
                                ->where('common_expense', true)
                                ->where('type','!=', 'pgto_de_fatura')
                                ->sum('amount'))
                            ->money('BRL'),
                        Summarizer::make()
                            ->label('Divisão por 2')
                            ->using(fn (QueryBuilder $query) => $query->select(DB::raw('sum(amount) / 2 as aggregate'))
                                ->where('common_expense', true)
                                ->where('type','!=', 'pgto_de_fatura')
                                ->value('aggregate'))
                            ->money('BRL')
                    ]),
            ])
            ->defaultSort(fn ($query) => $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc'))
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->hiddenLabel(),
                Tables\Actions\EditAction::make()->hiddenLabel(),
                Tables\Actions\DeleteAction::make()->hiddenLabel(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
