<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                TextColumn::make('creditCardBill.title_description_owner')->label('Fatura'),
                TextColumn::make('creditCardBill.due_date')->label('Fatura Vcto')->date('m/Y'),
                TextColumn::make('creditCardBill.owner_bill')->label('Dono'),
                TextColumn::make('transaction_date')->label('Data')->dateTime('d/m/Y'),
                TextColumn::make('description')->label('Descrição')
                    ->suffix(fn(Transaction $record): ?string => $record->origin ? ': ' . $record->origin : ''),
                TextColumn::make('installment_current')->label('Parcela')
                    ->badge()
                    ->state(fn (Transaction $record): ?string => $record->rotuloDaParcela())
                    ->placeholder('-'),
                TextColumn::make('amount')->money('BRL')->label('Valor')->sortable(),
                TextColumn::make('who_paid')->label('Pagador'),
                IconColumn::make('individual_expense')->label('Individual'),
            ])
            ->paginated([45])
            ->defaultSort('id', 'desc')
//            ->defaultSort(fn($query) => $query->orderBy('credit_card_bill_id', 'desc')
//                ->orderBy('transaction_date', 'asc')->orderBy('id', 'asc'))
            ->filtersFormColumns(3)
            ->filters([
                Filter::make('description')
                    ->label('Descrição')
                    ->schema([
                        TextInput::make('description')
                            ->label('Descrição')
                            ->placeholder('parte da descrição'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query->when(
                        filled($data['description'] ?? null),
                        fn(Builder $query): Builder => $query->where(
                            'description',
                            'like',
                            '%' . trim($data['description']) . '%',
                        ),
                    ))
                    ->indicateUsing(function (array $data): ?Indicator {
                        if (blank($data['description'] ?? null)) {
                            return null;
                        }

                        return Indicator::make('Descrição: ' . trim($data['description']))
                            ->removeField('description');
                    }),

                Filter::make('transaction_date')
                    ->label('Data')
                    ->schema([
                        DatePicker::make('de')->label('De')->native(false),
                        DatePicker::make('ate')->label('Até')->native(false),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when(
                            $data['de'] ?? null,
                            fn(Builder $query, $data): Builder => $query->whereDate('transaction_date', '>=', $data),
                        )
                        ->when(
                            $data['ate'] ?? null,
                            fn(Builder $query, $data): Builder => $query->whereDate('transaction_date', '<=', $data),
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicadores = [];

                        if ($data['de'] ?? null) {
                            $indicadores[] = Indicator::make('De ' . Carbon::parse($data['de'])->format('d/m/Y'))
                                ->removeField('de');
                        }

                        if ($data['ate'] ?? null) {
                            $indicadores[] = Indicator::make('Até ' . Carbon::parse($data['ate'])->format('d/m/Y'))
                                ->removeField('ate');
                        }

                        return $indicadores;
                    }),

                SelectFilter::make('credit_card_bill_id')
                    ->label('Fatura')
                    ->relationship(
                        'creditCardBill',
                        'title_description_owner',
                        fn(Builder $query): Builder => $query->orderByDesc('due_date'),
                    )
                    // owner_bill é nulo em fatura antiga; sem isso o rótulo
                    // sairia com um "()" vazio.
                    ->getOptionLabelFromRecordUsing(function (CreditCardBill $record): string {
                        $dono = $record->owner_bill ? " ({$record->owner_bill})" : '';

                        return $record->title_description_owner . $dono
                            . ' — venc. ' . $record->due_date->format('m/Y');
                    })
                    ->searchable(['title_description_owner', 'owner_bill'])
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make()->hiddenLabel()->hidden(function (Transaction $record) {
                    return $record->credit_card_bill_id !== null;
                }),
            ]);
//            ->toolbarActions([
//                BulkActionGroup::make([
//                    DeleteBulkAction::make(),
//                ]),
//            ]);
    }
}
