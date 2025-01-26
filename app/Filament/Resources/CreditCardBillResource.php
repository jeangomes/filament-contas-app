<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditCardBillResource\Pages;
use App\Filament\Resources\CreditCardBillResource\RelationManagers;
use App\Models\CreditCardBill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CreditCardBillResource extends Resource
{
    protected static ?string $model = CreditCardBill::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title_description_owner')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference_date')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->inputMode('decimal')
                    ->required(),
                Forms\Components\DatePicker::make('due_date')
                    ->required(),
                Forms\Components\Textarea::make('content_transaction')
                    ->required()
                    ->rows(10)
                    ->cols(20),
                Forms\Components\Select::make('type')
                    ->options([
                        'cat' => 'Cat',
                        'dog' => 'Dog',
                        'rabbit' => 'Rabbit',
                    ])->disabled(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_description_owner'),
                Tables\Columns\TextColumn::make('reference_date'),
                Tables\Columns\TextColumn::make('observation'),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('due_date'),
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
            'index' => Pages\ListCreditCardBills::route('/'),
            'create' => Pages\CreateCreditCardBill::route('/create'),
            'edit' => Pages\EditCreditCardBill::route('/{record}/edit'),
        ];
    }
}
