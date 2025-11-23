<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer:id,name'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('causer.name')->label('Usuário'),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')->timezone('America/Sao_Paulo')
                    ->label('Data')
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()->label('Ação'),
                TextColumn::make('ip_address')->label('IP'),
                // ip e navegador
            ])
            ->paginated([25])
            ->filters([
                //
            ])
            ->recordActions([
                //EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //DeleteBulkAction::make(),
                ]),
            ]);
    }
}
