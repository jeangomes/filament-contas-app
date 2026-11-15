<?php

declare(strict_types=1);

namespace App\Filament\Resources\MonthlySummaries\Schemas;

use App\Models\MonthlySummary;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

final class MonthlySummaryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Totais')
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('competencia')
                            ->label('Competência')
                            ->date('m/Y')
                            ->badge()
                            ->color('primary')
                            ->helperText(fn (MonthlySummary $record): string => 'gastos de ' . $record->reference_month->format('m/Y')),
                        TextEntry::make('total_home_expenses')->label('Despesas da casa')->money('BRL'),
                        TextEntry::make('total_common_expenses')->label('Total comum')->money('BRL'),
                        TextEntry::make('balance_description')
                            ->label('Acerto')
                            ->badge()
                            ->color(fn (MonthlySummary $record): string => $record->settlement_payer === null ? 'success' : 'warning')
                            ->helperText(fn (MonthlySummary $record): string => $record->difference_paid ? 'já acertado' : 'em aberto'),
                        TextEntry::make('calculation_notes')
                            ->label('Observações')
                            ->columnSpanFull()
                            ->visible(fn (MonthlySummary $record): bool => filled($record->calculation_notes)),
                    ]),

                Section::make('Por participante')
                    ->schema([
                        RepeatableEntry::make('participantSummaries')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Participante'),
                                TableColumn::make('Custo de vida')->alignment(Alignment::End),
                                TableColumn::make('Cartão (total)')->alignment(Alignment::End),
                                TableColumn::make('Cartão (comum)')->alignment(Alignment::End),
                                TableColumn::make('Cartão (individual)')->alignment(Alignment::End),
                                TableColumn::make('Pagou de comum')->alignment(Alignment::End),
                                TableColumn::make('Cota do comum')->alignment(Alignment::End),
                                TableColumn::make('Saldo')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('participant'),
                                TextEntry::make('living_cost')->money('BRL')->alignEnd(),
                                TextEntry::make('card_total')->money('BRL')->alignEnd(),
                                TextEntry::make('card_common')->money('BRL')->alignEnd(),
                                TextEntry::make('card_individual')->money('BRL')->alignEnd(),
                                TextEntry::make('paid_common')->money('BRL')->alignEnd(),
                                TextEntry::make('share_common')->money('BRL')->alignEnd(),
                                TextEntry::make('balance')->money('BRL')->alignEnd(),
                            ]),
                    ]),

                Section::make('Despesas da casa')
                    ->schema([
                        RepeatableEntry::make('homeExpenses')
                            ->hiddenLabel()
                            ->placeholder('Nenhuma despesa da casa nesta competência.')
                            ->table([
                                TableColumn::make('Descrição'),
                                TableColumn::make('Valor')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('label'),
                                TextEntry::make('amount')->money('BRL')->alignEnd(),
                            ]),
                    ]),
            ]);
    }
}
