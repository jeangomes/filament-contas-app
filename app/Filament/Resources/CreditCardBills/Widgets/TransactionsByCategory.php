<?php

namespace App\Filament\Resources\CreditCardBills\Widgets;

use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class TransactionsByCategory extends TableWidget
{
    protected static ?string $heading = 'Resumo por categoria';
    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $summary = Transaction::query()
            ->where('credit_card_bill_id', $this->record->getKey())
            ->where('description', '!=', 'Pagamento recebido')
            ->selectRaw('
            COALESCE(expense_category_id, 0) as id,
            expense_category_id,
            COUNT(*) as total_products,
            SUM(amount) as total_value
        ')
            ->groupBy('expense_category_id');

        return $table
            ->defaultSort('expense_category_id')
            ->paginated(false)
            ->query(
                (new Transaction)
                    ->setTable('summary')
                    ->newQuery()
                    ->fromSub($summary, 'summary')
            )
            ->columns([
                Tables\Columns\TextColumn::make('expenseCategory.description')->label('Categoria'),
                Tables\Columns\TextColumn::make('total_products')->label('Itens'),
                Tables\Columns\TextColumn::make('total_value')->label('Valor Total')->money('BRL'),
            ]);
    }
}
