<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Transação/Gasto';
    protected static ?string $pluralModelLabel = 'Transações/Gastos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('description')->label('Descrição')
                    ->required()
                    ->options([
                        'Aluguel' => 'Aluguel',
                        'Condominio' => 'Condominio',
                        'Eventualidades' => 'Eventualidades',
                        'LIGHT' => 'LIGHT',
                        'Naturgy' => 'Naturgy',
                        'Claro' => 'Claro',
                    ]),
                Forms\Components\TextInput::make('amount')->label('Valor')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required(),
                Forms\Components\DatePicker::make('transaction_date')->label('Data')
                    ->required(),
                Forms\Components\Radio::make('who_paid')->label('Quem pagou')
                    ->options([
                        'D' => 'D',
                        'J' => 'J',
                    ])
                    ->default('J')
                    ->inline()
                    ->required(),
                Forms\Components\Radio::make('common_expense')->label('Em comum')
                    ->boolean()
                    ->inline()
                    ->default(true)
                    ->required(),

                Forms\Components\Radio::make('individual_expense')->label('Individual')
                    ->boolean()
                    ->inline()
                    ->default(false)
                    ->required(),

            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('creditCardBill.title_description_owner')->label('Fatura'),
                Tables\Columns\TextColumn::make('creditCardBill.owner_bill')->label('Dono'),
                Tables\Columns\TextColumn::make('transaction_date')->label('Data')->dateTime('d/m/Y'),
                Tables\Columns\TextColumn::make('description')->label('Descrição'),
                Tables\Columns\TextColumn::make('parcelas'),
                Tables\Columns\TextColumn::make('amount')->money('BRL')->label('Valor'),
                //Tables\Columns\ToggleColumn::make('common_expense'),
                Tables\Columns\ToggleColumn::make('individual_expense')->label('Individual'),
                Tables\Columns\IconColumn::make('common_expense')
                    ->label('Em comum')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
