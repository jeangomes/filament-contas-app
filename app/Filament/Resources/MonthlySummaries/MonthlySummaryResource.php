<?php

declare(strict_types=1);

namespace App\Filament\Resources\MonthlySummaries;

use App\Filament\Resources\MonthlySummaries\Pages\ListMonthlySummaries;
use App\Filament\Resources\MonthlySummaries\Pages\ViewMonthlySummary;
use App\Filament\Resources\MonthlySummaries\Schemas\MonthlySummaryInfolist;
use App\Filament\Resources\MonthlySummaries\Tables\MonthlySummariesTable;
use App\Filament\Resources\MonthlySummaries\Widgets\UltimoFechamento;
use App\Models\MonthlySummary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * O histórico de fechamentos mensais já apurados ("Consolidado Mensal").
 *
 * Somente leitura: o fechamento é cache recalculável, criado e reconstruído
 * pelas ações da listagem via {@see \App\Services\FechamentoMensalService}.
 * O único campo editável é `difference_paid` (toggle na tabela), porque é o
 * único que não é calculado.
 *
 * O acesso é controlado pelo painel (`->login()` + `authMiddleware`).
 */
class MonthlySummaryResource extends Resource
{
    protected static ?string $model = MonthlySummary::class;

    protected static ?string $modelLabel = 'fechamento';

    protected static ?string $pluralModelLabel = 'fechamentos';

    protected static ?string $navigationLabel = 'Consolidado Mensal';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Resultados/Relatórios';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Eager load num ponto só: vale para a tabela e para o route binding da
     * página de detalhe. AppServiceProvider liga preventLazyLoading() fora de
     * produção, e as colunas por participante leem a relação linha a linha.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['participantSummaries', 'homeExpenses']);
    }

    public static function table(Table $table): Table
    {
        return MonthlySummariesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MonthlySummaryInfolist::configure($schema);
    }

    public static function getWidgets(): array
    {
        return [
            UltimoFechamento::class,
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
