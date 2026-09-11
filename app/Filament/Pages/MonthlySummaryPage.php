<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Models\MonthlyParticipantSummary;
use App\Models\MonthlySummary;
use App\Services\FechamentoMensalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Throwable;
use UnitEnum;

/**
 * O histórico de fechamentos mensais já apurados.
 *
 * Tudo aqui é leitura do que foi persistido — a apuração vive no domínio e
 * chega por {@see FechamentoMensalService}. O único campo editável é
 * `difference_paid`, porque é o único que não é calculado.
 *
 * O acesso é controlado pelo painel (`->login()` + `authMiddleware`), não por
 * autorização própria desta página.
 */
class MonthlySummaryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament.pages.monthly-summary-page';

    protected static ?string $title = 'Consolidado Mensal';

    protected static string|UnitEnum|null $navigationGroup = 'Resultados/Relatórios';

    protected static ?int $navigationSort = 1;

    private FechamentoMensalService $fechamento;

    private RepositorioDeLancamentos $repositorio;

    public function boot(FechamentoMensalService $fechamento, RepositorioDeLancamentos $repositorio): void
    {
        $this->fechamento = $fechamento;
        $this->repositorio = $repositorio;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Eager load obrigatório: AppServiceProvider liga
                // preventLazyLoading() fora de produção, e as colunas por
                // participante leem a relação linha a linha.
                MonthlySummary::query()->with(['participantSummaries', 'homeExpenses']),
            )
            ->defaultSort('competencia', 'desc')
            ->columns([
                TextColumn::make('competencia')
                    ->label('Competência')
                    ->date('m/Y')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->description(fn (MonthlySummary $registro): string => 'gastos de ' . $registro->reference_month->format('m/Y')),

                TextColumn::make('total_home_expenses')->label('Casa')->money('BRL')->sortable(),
                TextColumn::make('total_common_expenses')->label('Total comum')->money('BRL')->sortable(),

                $this->colunaDoParticipante(Participant::D, 'living_cost', 'Custo vida D')->color('success'),
                $this->colunaDoParticipante(Participant::J, 'living_cost', 'Custo vida J')->color('success'),

                $this->colunaDoParticipante(Participant::D, 'card_total', 'Cartão D')->color('info'),
                $this->colunaDoParticipante(Participant::J, 'card_total', 'Cartão J')->color('info'),

                $this->colunaDoParticipante(Participant::D, 'balance', 'Saldo D')
                    ->toggleable(isToggledHiddenByDefault: true),
                $this->colunaDoParticipante(Participant::J, 'balance', 'Saldo J')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('balance_description')
                    ->label('Acerto')
                    ->badge()
                    ->color(fn (MonthlySummary $registro): string => $registro->settlement_payer === null ? 'success' : 'warning'),

                // Único campo editável: é informação do usuário, não do cálculo,
                // e sobrevive ao recálculo.
                ToggleColumn::make('difference_paid')->label('Pago'),

                TextColumn::make('calculated_at')
                    ->label('Calculado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detalhe')
                    ->modalHeading(fn (MonthlySummary $registro): string => 'Fechamento de ' . $registro->competencia->format('m/Y'))
                    ->schema([
                        Section::make('Despesas da casa')
                            ->schema([
                                RepeatableEntry::make('homeExpenses')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('label')->label('Descrição'),
                                        TextEntry::make('amount')->label('Valor')->money('BRL'),
                                    ])
                                    ->columns(2),
                            ]),

                        Section::make('Por participante')
                            ->schema([
                                RepeatableEntry::make('participantSummaries')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('participant')->label('Participante'),
                                        TextEntry::make('living_cost')->label('Custo de vida')->money('BRL'),
                                        TextEntry::make('card_total')->label('Cartão (total)')->money('BRL'),
                                        TextEntry::make('card_common')->label('Cartão (comum)')->money('BRL'),
                                        TextEntry::make('card_individual')->label('Cartão (individual)')->money('BRL'),
                                        TextEntry::make('paid_common')->label('Pagou de comum')->money('BRL'),
                                        TextEntry::make('share_common')->label('Cota do comum')->money('BRL'),
                                        TextEntry::make('balance')->label('Saldo')->money('BRL'),
                                    ])
                                    ->columns(4),
                            ]),
                    ])
                    ->modalWidth('5xl'),
            ])
            ->emptyStateHeading('Nenhum fechamento calculado')
            ->emptyStateDescription('Use "Calcular mês" para apurar a primeira competência.')
            ->emptyStateIcon('heroicon-o-calculator');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calcular_mes')
                ->label('Calcular mês')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->schema([
                    Select::make('competencia')
                        ->label('Competência')
                        ->options($this->opcoesDeCompetencia())
                        ->default(Carbon::now()->format('Y-m'))
                        ->required()
                        ->helperText('O mês do vencimento das faturas — não o mês em que os gastos aconteceram.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $resumo = $this->fechamento->apurarEPersistir(
                            ReferenceMonth::fromString($data['competencia']),
                        );

                        Notification::make()
                            ->title('Competência fechada')
                            ->body($resumo->balance_description)
                            ->success()
                            ->send();

                        $this->resetTable();
                    } catch (Throwable $erro) {
                        $this->notificarErro($erro);
                    }
                })
                ->modalWidth('md'),

            Action::make('recalcular_tudo')
                ->label('Recalcular todos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Recalcular todo o histórico')
                ->modalDescription('Os números são reconstruídos a partir das transações. O que você marcou como pago e as observações não se perdem.')
                ->modalSubmitActionLabel('Recalcular')
                ->action(function (): void {
                    try {
                        $competencias = $this->repositorio->competenciasDisponiveis();

                        foreach ($competencias as $mes) {
                            $this->fechamento->apurarEPersistir($mes);
                        }

                        Notification::make()
                            ->title(count($competencias) . ' competência(s) recalculada(s)')
                            ->success()
                            ->send();

                        $this->resetTable();
                    } catch (Throwable $erro) {
                        $this->notificarErro($erro);
                    }
                }),
        ];
    }

    /**
     * O fechamento mais recente, para os cards do topo. `#[Computed]` evita
     * repetir a consulta a cada leitura na view.
     */
    #[Computed]
    public function ultimoFechamento(): ?MonthlySummary
    {
        return MonthlySummary::query()
            ->with('participantSummaries')
            ->orderByDesc('competencia')
            ->first();
    }

    public function formatMoney(string|float|int|null $valor): string
    {
        return Number::currency((float) ($valor ?? 0), in: 'BRL', locale: 'pt_BR');
    }

    private function colunaDoParticipante(Participant $participante, string $campo, string $rotulo): TextColumn
    {
        // Lê da relação já carregada: nenhuma consulta por linha.
        return TextColumn::make("{$participante->value}_{$campo}")
            ->label($rotulo)
            ->money('BRL')
            ->state(fn (MonthlySummary $registro): ?string => $registro
                ->participantSummary($participante)?->{$campo});
    }

    /**
     * Últimos 24 meses e os 3 próximos, como o select antigo oferecia.
     *
     * @return array<string, string>
     */
    private function opcoesDeCompetencia(): array
    {
        $opcoes = [];
        $inicio = Carbon::now()->addMonths(3);

        for ($i = 0; $i < 27; $i++) {
            $data = $inicio->copy()->subMonths($i);
            $opcoes[$data->format('Y-m')] = $data->translatedFormat('M/Y');
        }

        return $opcoes;
    }

    private function notificarErro(Throwable $erro): void
    {
        report($erro); // o detalhe vai para o log, não para a tela

        Notification::make()
            ->title('Não foi possível calcular')
            ->body($erro->getMessage())
            ->danger()
            ->send();
    }
}
