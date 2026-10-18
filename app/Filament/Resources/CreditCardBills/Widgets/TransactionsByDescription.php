<?php

namespace App\Filament\Resources\CreditCardBills\Widgets;

use App\Models\Transaction;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class TransactionsByDescription extends TableWidget
{
    protected static ?string $heading = 'Resumo agrupado por descrição';
    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $summary = Transaction::query()
            ->where('credit_card_bill_id', $this->record->getKey())
            ->where('description', '!=', 'Pagamento recebido')
            ->selectRaw('
            MIN(id) AS id,
            description,
            COUNT(*) as total_products,
            SUM(amount) as total_value
        ')
            ->groupBy('description');

        return $table
            ->defaultSort('description')
            ->paginated(false)
            ->query(
                (new Transaction)
                    ->setTable('summary')
                    ->newQuery()
                    ->fromSub($summary, 'summary')
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Descrição'),
                Tables\Columns\TextColumn::make('total_products')->label('Itens'),
                Tables\Columns\TextColumn::make('total_value')->label('Valor Total')->money('BRL'),
            ]);
    }
}
