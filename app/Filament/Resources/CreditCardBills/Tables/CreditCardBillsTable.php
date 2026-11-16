<?php

namespace App\Filament\Resources\CreditCardBills\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use App\Models\CreditCardBill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CreditCardBillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
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
                        return "<strong>" . $record->title_description_owner . ": $owner</strong>" .
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
                    ->dateTime('d/m/Y')
                    ->extraCellAttributes(function (CreditCardBill $record) {
                        return $record->due_date->format('Y-m') === date('Y-m') ? ['class' => 'my-bg-primary'] : [];
                    }),
                TextColumn::make('transactions_count')->counts('transactions')
                    ->label('Transações')->toggleable(isToggledHiddenByDefault: true),

                ColumnGroup::make('Valores', [
                    TextColumn::make('amount')->money('BRL')->label('Total Fatura'),
                    TextColumn::make('individual_amount')/*->sum([
                        'transactions' => fn(Builder $query) => $query->where('individual_expense', 1),
                    ], 'amount')*/ ->money('BRL')->label('Individual'),

                    TextColumn::make('common_amount')/*->sum([
                        'transactions as transactions_common' => fn(Builder $query) => $query->where('common_expense', '=', 1)
                    ], 'amount')*/ ->money('BRL')->label(new HtmlString('Total <br /> Comum')),

                    TextColumn::make('common_amount_divided_by_two')
                        ->state(function (CreditCardBill $record) {
                            return $record->common_amount / 2;
                        })
                        ->money('BRL')->label(new HtmlString('Divisão <br /> por 2')),
                ])
            ])
            ->defaultSort('due_date', 'desc')
            ->filters([
                Filter::make('vencimento')
                    ->label('Vencimento')
                    ->form([
                        Select::make('mes')->label('Mês do vencimento')
                            ->options(self::opcoesDeMes()),
                        Select::make('ano')->label('Ano do vencimento')
                            ->options(fn(): array => self::opcoesDeAno()),
                    ])
                    ->columns(2)
                    // whereYear/whereMonth, não DATE_FORMAT: os testes rodam em SQLite.
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when(
                            $data['mes'] ?? null,
                            fn(Builder $query, $mes): Builder => $query->whereMonth('due_date', (int) $mes),
                        )
                        ->when(
                            $data['ano'] ?? null,
                            fn(Builder $query, $ano): Builder => $query->whereYear('due_date', (int) $ano),
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicadores = [];

                        if ($data['mes'] ?? null) {
                            $indicadores[] = Indicator::make('Mês: ' . self::opcoesDeMes()[(int) $data['mes']])
                                ->removeField('mes');
                        }

                        if ($data['ano'] ?? null) {
                            $indicadores[] = Indicator::make('Ano: ' . $data['ano'])
                                ->removeField('ano');
                        }

                        return $indicadores;
                    }),

                SelectFilter::make('owner_bill')
                    ->label('Dono da fatura')
                    ->options([
                        'D' => 'Denilson',
                        'J' => 'Jean',
                    ]),
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

    /** @return array<int, string> */
    private static function opcoesDeMes(): array
    {
        $meses = [];

        foreach (range(1, 12) as $mes) {
            $meses[$mes] = ucfirst(Carbon::create(2000, $mes, 1)->getTranslatedMonthName());
        }

        return $meses;
    }

    /**
     * Os anos que têm fatura, do mais recente ao mais antigo. Extraídos em PHP
     * para não depender de função de data específica do banco.
     *
     * @return array<int, int>
     */
    private static function opcoesDeAno(): array
    {
        return CreditCardBill::query()
            ->pluck('due_date')
            ->filter()
            ->map(fn(Carbon $data): int => $data->year)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn(int $ano): array => [$ano => $ano])
            ->all();
    }
}
