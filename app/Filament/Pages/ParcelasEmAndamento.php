<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\Services\ApuradorDeParcelamentos;
use App\Domain\Finance\ValueObjects\CompromissoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\Parcelamento;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use BackedEnum;
use DateTimeImmutable;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * As compras parceladas em curso e o que elas já comprometem dos meses à
 * frente, em formato de relatório: posição, séries e projeção.
 *
 * Tudo é derivado das transações pelo domínio ({@see ApuradorDeParcelamentos});
 * não há nada persistido aqui — por isso é Page, não Resource (não há model).
 * A tabela recebe as séries prontas via `->records()`, e os filtros dela
 * valem também para o resumo e para a projeção.
 *
 * O acesso é controlado pelo painel, não por autorização própria da página.
 */
class ParcelasEmAndamento extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $title = 'Parcelas';

    protected static string|UnitEnum|null $navigationGroup = 'Resultados/Relatórios';

    protected static ?int $navigationSort = 2;

    private RepositorioDeLancamentos $repositorio;

    private ApuradorDeParcelamentos $apurador;

    /**
     * Cache por request das séries apuradas, sem filtro. Propriedade privada:
     * o Livewire não serializa, então cada request apura de novo.
     *
     * @var list<Parcelamento>|null
     */
    private ?array $todasAsSeries = null;

    public function boot(RepositorioDeLancamentos $repositorio, ApuradorDeParcelamentos $apurador): void
    {
        $this->repositorio = $repositorio;
        $this->apurador = $apurador;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (): string => 'Posição em ' . $this->formatCompetencia($this->competenciaAtual()))
                ->description('Considera os filtros da tabela abaixo.')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->schema([
                    TextEntry::make('resumo_series')
                        ->label('Séries em andamento')
                        ->state(fn (): int => count($this->emAndamento())),

                    TextEntry::make('resumo_proxima')
                        ->label('Próxima competência')
                        ->state(fn (): string => $this->formatMoney($this->compromissos()[0]->valor ?? Money::zero()))
                        ->helperText(fn (): ?string => isset($this->compromissos()[0])
                            ? $this->formatCompetencia($this->compromissos()[0]->mes)
                            : 'nada a vencer'),

                    TextEntry::make('resumo_total')
                        ->label('Total a vencer')
                        ->weight(FontWeight::SemiBold)
                        ->state(fn (): string => $this->formatMoney($this->totalComprometido())),

                    TextEntry::make('resumo_ultima')
                        ->label('Última parcela em')
                        ->state(fn (): string => $this->ultimaCompetenciaComprometida() ?? '—'),

                    TextEntry::make('resumo_defasadas')
                        ->label('Séries defasadas')
                        ->state(fn (): int => $this->quantidadeDefasadas())
                        ->color('warning')
                        ->helperText('fatura seguinte não importada')
                        ->visible(fn (): bool => $this->quantidadeDefasadas() > 0),
                ]),

            EmbeddedTable::make(),

            Section::make('Compromisso já assumido nos próximos meses')
                ->schema([
                    RepeatableEntry::make('compromissos')
                        ->hiddenLabel()
                        ->state(fn (): array => array_map(
                            fn (CompromissoMensal $compromisso): array => [
                                'competencia' => $this->formatCompetencia($compromisso->mes),
                                'valor' => $this->formatMoney($compromisso->valor),
                            ],
                            $this->compromissos(),
                        ))
                        ->placeholder('Nenhuma parcela a vencer.')
                        ->table([
                            TableColumn::make('Competência'),
                            TableColumn::make('Parcelas a vencer')->alignment(Alignment::End),
                        ])
                        ->schema([
                            TextEntry::make('competencia'),
                            TextEntry::make('valor')->alignEnd(),
                        ]),

                    TextEntry::make('compromisso_total')
                        ->label('Total')
                        ->inlineLabel()
                        ->weight(FontWeight::SemiBold)
                        ->state(fn (): string => $this->formatMoney($this->totalComprometido()))
                        ->visible(fn (): bool => $this->compromissos() !== []),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => $this->incluirQuitadas() ? 'Compras parceladas' : 'Parcelas em andamento')
            // A ordem é a do domínio: em andamento primeiro, depois o que
            // termina antes. Nada de ordenação por coluna.
            ->records(fn (): array => array_map($this->paraLinha(...), $this->parcelamentos()))
            ->paginated(false)
            ->columns([
                TextColumn::make('descricao')
                    ->label('Compra')
                    ->description(fn (array $record): string => $record['progresso'])
                    ->color(fn (array $record): ?string => $record['quitada'] ? 'gray' : null),
                TextColumn::make('pagador')->label('Pagador')->badge()->color('gray'),
                TextColumn::make('parcela')->label('Parcela')->badge(),
                TextColumn::make('valor_da_parcela')->label('Valor da parcela')->alignEnd(),
                TextColumn::make('total_pago')->label('Já pago')->alignEnd(),
                TextColumn::make('falta')->label('Falta')->alignEnd()->weight(FontWeight::SemiBold),
                TextColumn::make('termina_em')
                    ->label('Termina em')
                    ->color(fn (array $record): ?string => match (true) {
                        $record['quitada'] => 'gray',
                        $record['defasada'] => 'warning',
                        default => null,
                    })
                    ->tooltip(fn (array $record): ?string => $record['defasada'] ? 'A fatura seguinte não foi importada' : null),
            ])
            ->filters([
                Filter::make('incluir_quitadas')
                    ->label('Mostrar séries já quitadas')
                    ->toggle(),
                SelectFilter::make('pagador')
                    ->label('Pagador')
                    ->placeholder('Todos')
                    ->options(collect(Participant::cases())
                        ->mapWithKeys(static fn (Participant $p): array => [$p->value => $p->value])
                        ->all()),
            ], layout: FiltersLayout::AboveContent)
            ->emptyStateHeading('Nenhuma compra parcelada encontrada.')
            ->emptyStateDescription('Itens de fatura importados antes desta tela existir precisam de php artisan transactions:extrair-parcelas.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    /**
     * As séries que a tela mostra: as apuradas, filtradas pelo estado atual
     * dos filtros da tabela.
     *
     * O filtro é aplicado a cada leitura, de propósito. Com `#[Computed]` o
     * resultado ficava preso ao filtro da primeira leitura da request: ao
     * clicar em "Aplicar", algo lia as séries antes de `applyTableFilters()`
     * copiar o filtro novo, e a tela só respondia no segundo clique. O que é
     * caro (consulta + apuração) não depende de filtro e fica em
     * {@see self::todasAsSeries()}.
     *
     * @return list<Parcelamento>
     */
    public function parcelamentos(): array
    {
        $pagador = $this->pagadorFiltrado();
        $incluirQuitadas = $this->incluirQuitadas();

        return array_values(array_filter(
            $this->todasAsSeries(),
            static fn (Parcelamento $serie): bool => ($incluirQuitadas || $serie->emAndamento())
                && ($pagador === null || $serie->quemPagou->value === $pagador),
        ));
    }

    /**
     * A projeção sai das séries **em andamento** já filtradas: o que a tabela
     * mostra é o que o resumo soma.
     *
     * @return list<CompromissoMensal>
     */
    public function compromissos(): array
    {
        return $this->apurador->projetar($this->parcelamentos(), $this->competenciaAtual());
    }

    public function totalComprometido(): Money
    {
        $total = Money::zero();

        foreach ($this->compromissos() as $compromisso) {
            $total = $total->somar($compromisso->valor);
        }

        return $total;
    }

    /**
     * @return list<Parcelamento>
     */
    private function todasAsSeries(): array
    {
        return $this->todasAsSeries ??= $this->apurador->apurar($this->repositorio->parcelados());
    }

    private function competenciaAtual(): ReferenceMonth
    {
        return ReferenceMonth::fromDate(new DateTimeImmutable('today'));
    }

    /**
     * @return array<string, mixed>
     */
    private function paraLinha(Parcelamento $serie): array
    {
        $quitada = ! $serie->emAndamento();
        $defasada = $serie->defasadaEm($this->competenciaAtual());

        return [
            'key' => $serie->chave(),
            'descricao' => $serie->descricao,
            'progresso' => sprintf(
                '%d de %d pagas · %d%%',
                $serie->parcelasPagas,
                $serie->totalDeParcelas,
                (int) round($serie->parcelasPagas / $serie->totalDeParcelas * 100),
            ),
            'pagador' => $serie->quemPagou->value,
            'parcela' => $serie->rotulo(),
            'valor_da_parcela' => $this->formatMoney($serie->valorDaParcela),
            'total_pago' => $this->formatMoney($serie->totalPago),
            'falta' => $this->formatMoney($serie->valorRestante()),
            'termina_em' => match (true) {
                $quitada => 'quitada',
                $defasada => 'sem lançamento desde ' . $this->formatCompetencia($serie->ultimaCompetencia),
                default => $this->formatCompetencia($serie->competenciaFinal()),
            },
            'quitada' => $quitada,
            'defasada' => $defasada,
        ];
    }

    /**
     * @return list<Parcelamento>
     */
    private function emAndamento(): array
    {
        return array_values(array_filter(
            $this->parcelamentos(),
            static fn (Parcelamento $serie): bool => $serie->emAndamento(),
        ));
    }

    private function quantidadeDefasadas(): int
    {
        return count(array_filter(
            $this->emAndamento(),
            fn (Parcelamento $serie): bool => $serie->defasadaEm($this->competenciaAtual()),
        ));
    }

    /**
     * A competência da última parcela projetada — o mesmo horizonte da tabela
     * de compromisso, então série defasada não empurra a data para o passado.
     */
    private function ultimaCompetenciaComprometida(): ?string
    {
        $compromissos = $this->compromissos();

        return $compromissos === [] ? null : $this->formatCompetencia(end($compromissos)->mes);
    }

    private function incluirQuitadas(): bool
    {
        return (bool) ($this->tableFilters['incluir_quitadas']['isActive'] ?? false);
    }

    private function pagadorFiltrado(): ?string
    {
        $valor = $this->tableFilters['pagador']['value'] ?? null;

        return ($valor === null || $valor === '') ? null : (string) $valor;
    }

    /**
     * Formata a partir da representação decimal exata do `Money` — os
     * centavos nunca passam por float no caminho até a tela.
     */
    private function formatMoney(Money $valor): string
    {
        [$inteiro, $centavos] = explode('.', ltrim($valor->paraDecimal(), '-'));
        $sinal = $valor->ehNegativo() ? '-' : '';

        return $sinal . 'R$ ' . number_format((int) $inteiro, 0, ',', '.') . ',' . $centavos;
    }

    private function formatCompetencia(ReferenceMonth $mes): string
    {
        return sprintf('%02d/%04d', $mes->numeroDoMes(), $mes->ano());
    }
}
