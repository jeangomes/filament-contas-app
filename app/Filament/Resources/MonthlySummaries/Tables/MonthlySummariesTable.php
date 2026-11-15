<?php

declare(strict_types=1);

namespace App\Filament\Resources\MonthlySummaries\Tables;

use App\Domain\Finance\Enums\Participant;
use App\Models\MonthlySummary;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use function Laravel\Prompts\number;

final class MonthlySummariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('competencia', 'desc')
            ->columns([
                TextColumn::make('competencia')
                    ->label('Competência')
                    ->date('m/Y')
                    ->sortable()
                    //->badge()
                    ->color('primary')
                    ->description(fn (MonthlySummary $registro): string => 'ref ' . $registro->reference_month->format('m/Y')),

                TextColumn::make('total_home_expenses')->label('Casa')->money('BRL')->sortable(),
                TextColumn::make('total_common_expenses')->label('Total comum')->money('BRL')->sortable(),

                self::colunaDoParticipante(Participant::D, 'living_cost', 'Custo vida D')->color('success'),
                self::colunaDoParticipante(Participant::J, 'living_cost', 'Custo vida J')->color('success'),

                self::colunaDoParticipante(Participant::D, 'card_total', 'Cartão D')->color('info'),
                self::colunaDoParticipante(Participant::J, 'card_total', 'Cartão J')->color('info'),

                self::colunaDoParticipante(Participant::D, 'balance', 'Saldo D')
                    ->toggleable(isToggledHiddenByDefault: true),
                self::colunaDoParticipante(Participant::J, 'balance', 'Saldo J')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('balance_description')
                    ->label('Acerto')
                    ->badge()->html()
                    ->color(fn (MonthlySummary $registro): string => $registro->settlement_payer === null ? 'success' : 'warning'),

                // Único campo editável: é informação do usuário, não do cálculo,
                // e sobrevive ao recálculo. Não depende de canEdit() do Resource.
                ToggleColumn::make('difference_paid')->label('Pago'),

                TextColumn::make('calculated_at')
                    ->label('Calculado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make()->hiddenLabel(),//->label(null),
            ])
            ->emptyStateHeading('Nenhum fechamento calculado')
            ->emptyStateDescription('Use "Calcular mês" para apurar a primeira competência.')
            ->emptyStateIcon('heroicon-o-calculator');
    }

    private static function colunaDoParticipante(Participant $participante, string $campo, string $rotulo): TextColumn
    {
        // Lê da relação já carregada (getEloquentQuery do Resource): nenhuma
        // consulta por linha.
        return TextColumn::make("{$participante->value}_$campo")
            ->label($rotulo)
            ->money('BRL')
            ->state(fn (MonthlySummary $registro): ?string => $registro
                ->participantSummary($participante)?->{$campo});
    }
}
