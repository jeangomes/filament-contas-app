<?php

namespace App\Filament\Resources\MonthlySummaries;

use App\Filament\Resources\MonthlySummaries\Pages\ListMonthlySummaries;
use App\Filament\Resources\MonthlySummaries\Pages\ViewMonthlySummary;
use App\Filament\Resources\MonthlySummaries\Schemas\MonthlySummaryForm;
use App\Filament\Resources\MonthlySummaries\Schemas\MonthlySummaryInfolist;
use App\Filament\Resources\MonthlySummaries\Tables\MonthlySummariesTable;
use App\Models\MonthlySummary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MonthlySummaryResource extends Resource
{
    protected static ?string $model = MonthlySummary::class;

    protected static ?string $modelLabel = 'Resumo mensal';
    protected static ?string $pluralModelLabel = 'Resumos mensais';

    protected static string | UnitEnum | null $navigationGroup = 'Resultados/Relatórios';
    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MonthlySummaryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MonthlySummaryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MonthlySummariesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
            'index' => ListMonthlySummaries::route('/'),
            'view' => ViewMonthlySummary::route('/{record}'),
        ];
    }
}
