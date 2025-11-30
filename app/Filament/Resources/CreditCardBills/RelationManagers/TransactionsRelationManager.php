<?php

namespace App\Filament\Resources\CreditCardBills\RelationManagers;

use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\DB;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $modelLabel = 'transação';
    protected static ?string $title = 'Transações';

    public function canCreate(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')->label('Tipo')
                    ->required()
                    ->options([
                        'fixed_expense' => 'fixed_expense',
                        'pgto_de_fatura' => 'pgto_de_fatura',
                    ]),
                //TransactionResource::fieldDescription(),
                TransactionForm::fieldOrigin(),
                TransactionForm::fieldAmount(),
                TransactionForm::fieldTransactionDate(),
                TransactionForm::fieldWhoPaid(),
                TransactionForm::fieldCommon(),
                TransactionForm::fieldIndividual(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('transaction_date')
                    ->date(),
                TextEntry::make('description'),
                TextEntry::make('parcelas')
                    ->placeholder('-'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('responsible_for_expense')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('who_paid')
                    ->badge()
                    ->placeholder('-'),
                IconEntry::make('common_expense')
                    ->boolean()
                    ->placeholder('-'),
                IconEntry::make('individual_expense')
                    ->boolean()
                    ->placeholder('-'),
                IconEntry::make('mov_type')
                    ->boolean(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('expense_category_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('origin')
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordUrl(null)->recordAction(null)
            ->recordClasses(fn(Transaction $record) => match ($record->individual_expense) {
                true => 'my-bg-primary',
                false => '',
            })
            ->emptyStateDescription('Depois que você salvar a primeira versão, ela aparecerá aqui.')
            ->recordTitleAttribute('description')
            ->paginated(false)
            ->columns([
                TextColumn::make('transaction_date')->label('Data')->dateTime('d/m/Y'),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->summarize(Count::make()),
                TextColumn::make('parcelas'),
                TextColumn::make('amount')->money('BRL')->label('Valor')
                    ->summarize(Sum::make()->query(fn(QueryBuilder $query) => $query->where('type', '!=', 'pgto_de_fatura'))->money('BRL')),
                ToggleColumn::make('individual_expense')
                    ->label('Gasto Individual')
                    ->beforeStateUpdated(function ($record, $state) {
                        //dd($record, $state);
                        $record->common_expense = !$state;
                        // Runs before the state is saved to the database.
                    })
                    ->afterStateUpdated(function ($record, $state) {
                        // Runs after the state is saved to the database.
                    })
                    ->summarize([
                        Count::make()->query(fn(QueryBuilder $query) => $query->where('individual_expense', true)),
                        Summarizer::make()
                            ->label('Total Individual')
                            ->using(fn(QueryBuilder $query): string => $query
                                ->where('individual_expense', true)
                                ->where('type', '!=', 'pgto_de_fatura')
                                ->sum('amount'))
                            ->money('BRL'),
                    ]),
                IconColumn::make('common_expense')
                    ->label('Gasto em comum')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->summarize([
                        Count::make()->query(fn(QueryBuilder $query) => $query->where('common_expense', true)),
                        Summarizer::make()
                            ->label('Total comum')
                            ->using(fn(QueryBuilder $query): string => $query
                                ->where('common_expense', true)
                                ->where('type', '!=', 'pgto_de_fatura')
                                ->sum('amount'))
                            ->money('BRL'),
                        Summarizer::make()
                            ->label('Divisão por 2')
                            ->using(fn(QueryBuilder $query) => $query->select(DB::raw('sum(amount) / 2 as aggregate'))
                                ->where('common_expense', true)
                                ->where('type', '!=', 'pgto_de_fatura')
                                ->value('aggregate'))
                            ->money('BRL')
                    ]),
            ])
            ->defaultSort(fn($query) => $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc'))
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make()->hiddenLabel(),
                EditAction::make()->hiddenLabel(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
