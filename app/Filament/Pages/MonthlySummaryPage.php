<?php

namespace App\Filament\Pages;

use App\Services\MonthlySummaryService;
use Exception;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use BackedEnum;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Tables\Concerns\InteractsWithTable;
use UnitEnum;
use App\Models\MonthlySummary;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class MonthlySummaryPage extends Page implements HasTable
{
    use InteractsWithTable;
    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament.pages.monthly-summary-page';
    protected static ?string $title = 'Consolidado (Armazenado)';
    protected static string | UnitEnum | null $navigationGroup = 'Resultados/Relatórios';
    protected static ?int $navigationSort = 3;

    private MonthlySummaryService $summaryService;

    public function boot(MonthlySummaryService $summaryService): void
    {
        $this->summaryService = $summaryService;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Action para calcular um mês específico
            Action::make('calculate_month')
                ->label('Calcular Mês')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->form([
                    Select::make('reference_month')
                        ->label('Mês de Referência')
                        ->options($this->getMonthOptions())
                        ->default(Carbon::now()->subMonth()->format('Y-m'))
                        ->required()
                        ->helperText('Selecione o mês que deseja calcular'),
                ])
                ->action(function (array $data): void {
                    try {
                        $summary = $this->summaryService->calculateAndPersistSummary($data['reference_month']);

                        Notification::make()
                            ->title('✅ Resumo Calculado!')
                            ->body("Mês {$data['reference_month']} calculado com sucesso")
                            ->success()
                            ->duration(5000)
                            ->send();

                        // Refresh da tabela
                        $this->resetTable();

                    } catch (Exception $e) {
                        Notification::make()
                            ->title('❌ Erro no Cálculo')
                            ->body($e->getMessage())
                            ->danger()
                            ->duration(8000)
                            ->send();
                    }
                })
                ->modalWidth(500),

            // Action para recalcular últimos 6 meses
//            Action::make('recalculate_recent')
//                ->label('Recalcular Últimos 6 Meses')
//                ->icon('heroicon-o-arrow-path')
//                ->color('warning')
//                ->requiresConfirmation()
//                ->modalHeading('Recalcular Últimos 6 Meses')
//                ->modalDescription('Esta ação irá recalcular os resumos dos últimos 6 meses. Dados existentes serão sobrescritos.')
//                ->modalSubmitActionLabel('Sim, Recalcular')
//                ->action(function (): void {
//                    try {
//                        $months = collect();
//                        $currentMonth = Carbon::now()->subMonth();
//
//                        for ($i = 0; $i < 6; $i++) {
//                            $months->push($currentMonth->format('Y-m'));
//                            $currentMonth->subMonth();
//                        }
//
//                        $results = $this->summaryService->recalculateMultipleMonths($months->toArray());
//                        $successful = collect($results)->filter()->count();
//
//                        Notification::make()
//                            ->title('✅ Recálculo Concluído!')
//                            ->body("{$successful} de {$months->count()} meses recalculados com sucesso")
//                            ->success()
//                            ->duration(5000)
//                            ->send();
//
//                        $this->resetTable();
//
//                    } catch (\Exception $e) {
//                        Notification::make()
//                            ->title('❌ Erro no Recálculo')
//                            ->body($e->getMessage())
//                            ->danger()
//                            ->duration(8000)
//                            ->send();
//                    }
//                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MonthlySummary::query())
            ->defaultSort('reference_month', 'desc')
            ->columns([
                TextColumn::make('reference_month')
                    ->label('Mês Ref.')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('due_payment_month')
                    ->label('Mês Vcto.')
                    ->dateTime('M/Y')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_home_expenses')
                    ->label('Despesas Casa')
                    ->sortable(),

                TextColumn::make('d_credit_card_total')
                    ->label('Cartão D')
                    ->color('info'),

                TextColumn::make('j_credit_card_total')
                    ->label('Cartão J')
                    ->color('info'),

                TextColumn::make('balance_description')
                    ->label('Saldo')
                    ->badge()
                    ->color(fn (MonthlySummary $record): string =>
                    $record->balance_difference == 0 ? 'success' : 'warning'
                    ),

                TextColumn::make('d_living_cost')
                    ->label('Custo Vida D')
                    ->color('success'),

                TextColumn::make('j_living_cost')
                    ->label('Custo Vida J')
                    ->color('success'),

                TextColumn::make('calculated_at')
                    ->label('Calculado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ViewAction::make()
                    ->form([
                        // Despesas da Casa
                        Section::make('Despesas da Casa')
                            ->schema([
                               TextInput::make('rent_apartment')
                                    ->label('Aluguel')
                                    ->disabled(),
                               TextInput::make('condo_apartment')
                                    ->label('Condomínio')
                                    ->disabled(),
                               TextInput::make('eventual_apartment')
                                    ->label('Eventuais')
                                    ->disabled(),
                               TextInput::make('light_bill')
                                    ->label('Light')
                                    ->disabled(),
                               TextInput::make('naturgy_bill')
                                    ->label('Naturgy')
                                    ->disabled(),
                               TextInput::make('claro_bill')
                                    ->label('Claro')
                                    ->disabled(),
                               TextInput::make('total_home_expenses')
                                    ->label('Total Casa')
                                    ->disabled()
                                    ->extraAttributes(['class' => 'font-bold']),
                            ])
                            ->columns(3),

                        // Cartões de Crédito
                        Section::make('Cartões de Crédito')
                            ->schema([
                                Fieldset::make('Participante D')
                                    ->schema([
                                       TextInput::make('d_credit_card_total')
                                            ->label('Total D')
                                            ->disabled(),
                                       TextInput::make('d_credit_card_common')
                                            ->label('Comum D')
                                            ->disabled(),
                                       TextInput::make('d_credit_card_individual')
                                            ->label('Individual D')
                                            ->disabled(),
                                    ]),

                               Fieldset::make('Participante J')
                                    ->schema([
                                       TextInput::make('j_credit_card_total')
                                            ->label('Total J')
                                            ->disabled(),
                                       TextInput::make('j_credit_card_common')
                                            ->label('Comum J')
                                            ->disabled(),
                                       TextInput::make('j_credit_card_individual')
                                            ->label('Individual J')
                                            ->disabled(),
                                    ]),
                            ])
                            ->columns(2),

                        // Resumo Final
                        Section::make('Resumo Final')
                            ->schema([
                                TextInput::make('balance_difference')
                                    ->label('Diferença')
                                    ->disabled(),
                                TextInput::make('balance_payer')
                                    ->label('Quem Paga')
                                    ->disabled(),
                                TextInput::make('d_living_cost')
                                    ->label('Custo de Vida D')
                                    ->disabled(),
                                TextInput::make('j_living_cost')
                                    ->label('Custo de Vida J')
                                    ->disabled(),
                            ])
                            ->columns(2),
                    ])
                    ->modalWidth(700),

                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading('Nenhum resumo encontrado')
            ->emptyStateDescription('Clique em "Calcular Mês" para gerar o primeiro resumo')
            ->emptyStateIcon('heroicon-o-calculator');
    }

    /**
     * Gera opções de meses para o select (últimos 24 meses + próximos 3)
     */

    private function getMonthOptions(): array
    {
        $options = [];
        $start = Carbon::now()->addMonths(3);

        for ($i = 0; $i < 27; $i++) {
            $date = $start->copy()->subMonths($i);

            // Exemplo: "fev/2025"
            $label = $date->translatedFormat('M/Y'); // ← pt_BR

            $options[$date->format('Y-m')] = $label;
        }

        return $options;
    }

    /**
     * Formatar valores monetários
     */
    public function formatMoney($value): string
    {
        return Number::currency($value, in: 'BRL', locale: 'pt_BR');
    }

}
