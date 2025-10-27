<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    protected static ?string $pluralModelLabel = 'tracking';
    //protected static ?string $navigationLabel = 'Custom Navigation Label';
    protected static ?int $navigationSort = 5;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    //protected static bool $shouldRegisterNavigation = false;
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }


    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer:id,name'))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('causer.name')->label('Usuário'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')->timezone('America/Sao_Paulo')
                    ->label('Data')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()->label('Ação'),
                // ip e navegador
            ])
            ->filters([
                //
            ])
            ->actions([
                //Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListActivityLogs::route('/'),
            //'create' => Pages\CreateActivityLog::route('/create'),
            'edit' => Pages\EditActivityLog::route('/{record}/edit'),
        ];
    }
}
