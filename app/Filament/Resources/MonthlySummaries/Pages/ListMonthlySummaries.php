<?php

declare(strict_types=1);

namespace App\Filament\Resources\MonthlySummaries\Pages;

use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use App\Filament\Resources\MonthlySummaries\Widgets\UltimoFechamento;
use App\Services\FechamentoMensalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Tudo aqui é leitura do que foi persistido — a apuração vive no domínio e
 * chega por {@see FechamentoMensalService}.
 */
class ListMonthlySummaries extends ListRecords
{
    protected static string $resource = MonthlySummaryResource::class;

    protected static ?string $title = 'Consolidado Mensal';
    protected ?string $subheading = 'Calcular e consolidar o fechamento mensal';

    private FechamentoMensalService $fechamento;

    private RepositorioDeLancamentos $repositorio;

    public function boot(FechamentoMensalService $fechamento, RepositorioDeLancamentos $repositorio): void
    {
        $this->fechamento = $fechamento;
        $this->repositorio = $repositorio;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UltimoFechamento::class,
        ];
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

                        $this->aposRecalcular();
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

                        $this->aposRecalcular();
                    } catch (Throwable $erro) {
                        $this->notificarErro($erro);
                    }
                }),

            Action::make('como_funciona')
                ->label('Como funciona')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Como funciona')
                ->modalWidth('3xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Group::make([
                            Text::make('Calcular mês')->weight(FontWeight::SemiBold),
                            UnorderedList::make([
                                Text::make(new HtmlString('A competência é o mês do <strong>vencimento</strong> das faturas.')),
                                Text::make('Os números são apurados das transações, não digitados.'),
                                Text::make('Recalcular quantas vezes quiser é seguro: substitui, não duplica.'),
                                Text::make(new HtmlString('O que você marca como <strong>Pago</strong> sobrevive ao recálculo.')),
                            ]),
                        ]),
                        Group::make([
                            Text::make('Custo de vida × saldo')->weight(FontWeight::SemiBold),
                            UnorderedList::make([
                                Text::make(new HtmlString('<strong>Custo de vida</strong>: quanto a vida de cada um custou no mês.')),
                                Text::make(new HtmlString('<strong>Saldo</strong>: quem adiantou dinheiro e quem ficou devendo.')),
                                Text::make('São perguntas diferentes — não se somam.'),
                                Text::make('Em "Detalhe" você vê as despesas da casa e os números completos.'),
                            ]),
                        ]),
                    ]),
                ]),
        ];
    }

    private function aposRecalcular(): void
    {
        $this->resetTable();
        $this->dispatch(UltimoFechamento::EVENTO_ATUALIZADO);
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
