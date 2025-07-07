<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Transação/Gasto';
    protected static ?string $pluralModelLabel = 'Transações/Gastos';
    protected static ?int $navigationSort = 2;

    public static function fieldIndividual(): Forms\Components\Radio
    {
        return Forms\Components\Radio::make('individual_expense')->label('Individual')
            ->boolean()
            ->inline()
            ->default(false)
            ->required();
    }

    public static function fieldCommon(): Forms\Components\Radio
    {
        return Forms\Components\Radio::make('common_expense')->label('Em comum')
            ->boolean()
            ->inline()
            ->default(true)
            ->required();
    }

    public static function form(Form $form): Form
    {
        if ($form->getOperation() === 'create') {
            return $form
                ->schema([
                    Repeater::make('transactions')
                        ->schema([
                            self::fieldDescription(),
                            self::fieldOrigin(),
                            self::fieldAmount(),
                            self::fieldTransactionDate(),
                            self::fieldWhoPaid(),
                            self::fieldCommon(),
                            self::fieldIndividual(),
                        ])->cloneable()
                        ->columns(3),

                ])->columns(1);
        }
        return $form
            ->schema([
                self::fieldDescription(),
                self::fieldOrigin(),
                self::fieldAmount(),
                self::fieldTransactionDate(),
                self::fieldWhoPaid(),
                self::fieldCommon(),
                self::fieldIndividual(),
            ])
            ->columns(3);

    }

    public static function table(Table $table): Table
    {
        return $table->paginated([25])
            ->columns([
                Tables\Columns\TextColumn::make('creditCardBill.title_description_owner')->label('Fatura'),
                Tables\Columns\TextColumn::make('creditCardBill.owner_bill')->label('Dono'),
                Tables\Columns\TextColumn::make('transaction_date')->label('Data')->dateTime('d/m/Y'),
                Tables\Columns\TextColumn::make('description')->label('Descrição')
                    ->suffix(fn(Transaction $record): ?string => $record->origin ? ': ' . $record->origin : ''),
                Tables\Columns\TextColumn::make('parcelas')->label('Parcelas'),
                Tables\Columns\TextColumn::make('amount')->money('BRL')->label('Valor'),
                Tables\Columns\TextColumn::make('who_paid')->label('Pagador'),
                Tables\Columns\IconColumn::make('individual_expense')->label('Individual'),
                Tables\Columns\IconColumn::make('common_expense')
                    ->label('Em comum')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Excluir'),
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

    /**
     * @return Forms\Components\Radio
     */
    public static function fieldWhoPaid(): Forms\Components\Radio
    {
        return Forms\Components\Radio::make('who_paid')->label('Quem pagou')
            ->options([
                'D' => 'D',
                'J' => 'J',
            ])
            ->default('J')
            ->inline()
            ->required();
    }

    /**
     * @return Forms\Components\DatePicker
     */
    public static function fieldTransactionDate(): Forms\Components\DatePicker
    {
        return Forms\Components\DatePicker::make('transaction_date')->label('Data')
            ->required();
    }

    /**
     * @return Forms\Components\TextInput
     */
    public static function fieldAmount(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('amount')->label('Valor')
            ->numeric()
            ->inputMode('decimal')
            ->required();
    }

    /**
     * @return Forms\Components\TextInput
     */
    public static function fieldOrigin(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('origin')->label('Origem')
            ->required(fn(Get $get): bool => $get('description') === 'Outros')
            ->visible(fn(Get $get): bool => $get('description') === 'Outros');
    }

    /**
     * @return Forms\Components\Select
     */
    public static function fieldDescription(): Forms\Components\Select
    {
        return Forms\Components\Select::make('description')->label('Descrição')
            ->required()
            ->options([
                'Aluguel' => 'Aluguel',
                'Condomínio' => 'Condomínio',
                'Eventualidades' => 'Eventualidades (iptu, taxas)',
                'LIGHT' => 'LIGHT',
                'Naturgy' => 'Naturgy',
                'Claro' => 'Claro',
                'Outros' => 'Outros gastos (não fixos)',
            ])->live();
    }
}
