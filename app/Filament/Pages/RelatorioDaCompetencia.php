<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\Rubrica;
use App\Domain\Finance\Exceptions\CompetenciaInvalida;
use App\Domain\Finance\ValueObjects\DespesaDaCasa;
use App\Domain\Finance\ValueObjects\FechamentoDoParticipante;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Filament\Concerns\FormataValores;
use App\Filament\Resources\CreditCardBills\CreditCardBillResource;
use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Infra\Excel\ExportadorDoRelatorioXlsx;
use App\Services\FechamentoMensalService;
use App\Services\Relatorio\Conferencia;
use App\Services\Relatorio\LinhaDoRelatorio;
use App\Services\Relatorio\RelatorioDaCompetencia as Relatorio;
use App\Services\RelatorioDaCompetenciaService;
use BackedEnum;
use Closure;
use DateTimeImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use UnitEnum;

/**
 * A competência explicada: de onde sai cada número do fechamento, na ordem
 * do raciocínio — rateio, dívidas cruzadas, saldo e acerto, custo de vida e
 * origem dos gastos.
 *
 * Calculado ao vivo das transações por {@see RelatorioDaCompetenciaService};
 * nada é lido do fechamento salvo, que só entra para avisar quando está
 * desatualizado. Por isso é Page, não Resource, e funciona para mês ainda não
 * fechado.
 *
 * Os resultados vêm do domínio (`fechamento`); as somas das linhas servem aos
 * subtotais e às conferências — ver {@see Relatorio}.
 *
 * O acesso é controlado pelo painel, não por autorização própria da página.
 */
class RelatorioDaCompetencia extends Page implements HasTable
{
    use FormataValores;
    use InteractsWithTable;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $title = 'Relatório Mensal';

    protected static string|UnitEnum|null $navigationGroup = 'Resultados/Relatórios';

    protected static ?int $navigationSort = 3;

    /** Competência (mês do vencimento), `Y-m`. Vai na URL: o link abre no mesmo mês. */
    #[Url]
    public string $mes = '';

    private RelatorioDaCompetenciaService $servico;

    private ExportadorDoRelatorioXlsx $exportador;

    private FechamentoMensalService $fechamento;

    /**
     * Cache por request (o Livewire não serializa privadas). Guardado junto
     * com o mês: uma action troca o mês depois de algo já ter lido o
     * relatório na mesma request.
     */
    private ?Relatorio $relatorio = null;

    private ?string $mesDoRelatorio = null;

    public function boot(
        RelatorioDaCompetenciaService $servico,
        ExportadorDoRelatorioXlsx $exportador,
        FechamentoMensalService $fechamento,
    ): void {
        $this->servico = $servico;
        $this->exportador = $exportador;
        $this->fechamento = $fechamento;
    }

    public function mount(): void
    {
        $this->mes = $this->mesValido($this->mes)->chave();
    }

    /** Escolha no select: normaliza antes de qualquer leitura. */
    public function updatedMes(): void
    {
        $this->mes = $this->mesValido($this->mes)->chave();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $competencia = $this->competencia();

        return sprintf(
            'Competência %s · gastos de %s',
            $this->formatCompetencia($competencia),
            $this->formatCompetencia($competencia->mesAnterior()),
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('mes')
                ->label('Competência')
                ->helperText('O mês do vencimento das faturas — não o mês em que os gastos aconteceram.')
                ->options($this->opcoesDeMes())
                ->selectablePlaceholder(false)
                ->live()
                ->maxWidth('xs'),

            $this->secaoResumo(),
            $this->secaoRateio(),
            $this->secaoDividasCruzadas(),
            $this->secaoSaldoEAcerto(),
            $this->secaoCustoDeVida(),
            $this->secaoOrigem(),

            EmbeddedTable::make(),
        ]);
    }

    /**
     * Todos os lançamentos da competência, inclusive os fora do rateio: é
     * por aqui que qualquer número das seções acima se rastreia até as
     * linhas que o compõem.
     *
     * Dados próprios (`->records()`), então nada é automático: filtro e busca
     * são aplicados em {@see self::linhasFiltradas()}, a ordem é a da
     * consulta (data da compra) e não há summarizer — o total do que está
     * filtrado vai na descrição.
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('Lançamentos da competência')
            ->description(fn (): string => $this->resumoDasLinhasFiltradas())
            ->records(fn (): array => array_map($this->paraLinhaDaTabela(...), $this->linhasFiltradas()))
            ->paginated(false)
            ->searchable()
            ->searchPlaceholder('Buscar na descrição')
            ->columns([
                TextColumn::make('data')->label('Compra')->color($this->corDaLinha(...)),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->description(fn (array $record): ?string => $record['parcela'])
                    ->color($this->corDaLinha(...))
                    ->wrap(),
                TextColumn::make('origem')->label('Origem')->color('gray')->wrap(),
                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pagou')->label('Pagou')->badge()->color('gray'),
                TextColumn::make('responsavel')
                    ->label('Responsável')
                    ->color($this->corDaLinha(...))
                    ->tooltip(fn (array $record): ?string => $record['responsavel_assumido']
                        ? 'Sem responsável informado — assume quem pagou'
                        : null),
                TextColumn::make('rubrica')
                    ->label('Rubrica')
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => Rubrica::from($state)->rotulo())
                    ->color(static fn (string $state): string => match (Rubrica::from($state)) {
                        Rubrica::ComumDeCartao => 'info',
                        Rubrica::ComumDaCasa => 'primary',
                        Rubrica::DividaCruzada => 'warning',
                        Rubrica::IndividualPropria, Rubrica::ForaDoRateio => 'gray',
                    })
                    ->tooltip(static fn (string $state): string => Rubrica::from($state)->explicacao()),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->alignEnd()
                    ->color(fn (array $record): ?string => match (true) {
                        $record['fora_do_rateio'] => 'gray',
                        $record['estorno'] => 'success',
                        default => null,
                    }),
            ])
            ->filters([
                SelectFilter::make('rubrica')
                    ->label('Rubrica')
                    ->multiple()
                    ->options(collect(Rubrica::cases())
                        ->mapWithKeys(static fn (Rubrica $r): array => [$r->value => $r->rotulo()])
                        ->all()),
                SelectFilter::make('pagou')
                    ->label('Quem pagou')
                    ->placeholder('Todos')
                    ->options(collect(Participant::cases())
                        ->mapWithKeys(static fn (Participant $p): array => [$p->value => $p->value])
                        ->all()),
                SelectFilter::make('origem')
                    ->label('Origem')
                    ->placeholder('Todas')
                    ->options(['cartao' => 'Cartão', 'avulso' => 'Avulso']),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->hiddenLabel()
                    ->tooltip(fn (array $record): string => $record['fatura_id'] === null ? 'Abrir lançamento' : 'Abrir fatura')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (array $record): string => $record['fatura_id'] === null
                        ? TransactionResource::getUrl('edit', ['record' => $record['key']])
                        : CreditCardBillResource::getUrl('edit', ['record' => $record['fatura_id']]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Nenhum lançamento encontrado.')
            ->emptyStateDescription('Não há lançamentos nesta competência com os filtros atuais.')
            ->emptyStateIcon('heroicon-o-document-magnifying-glass');
    }

    /**
     * As linhas que a tabela mostra. Filtro e busca são lidos a cada chamada,
     * de propósito: cachear o resultado filtrado prende a tela ao filtro
     * antigo (o filtro "deferred" e o clique em Aplicar chegam na mesma
     * request). O que é caro — consulta e apuração — está em
     * {@see self::relatorio()}.
     *
     * Vale só para a tabela: as seções acima são sempre do mês inteiro.
     *
     * @return list<LinhaDoRelatorio>
     */
    private function linhasFiltradas(): array
    {
        $rubricas = array_values(array_filter((array) ($this->tableFilters['rubrica']['values'] ?? [])));
        $pagou = $this->filtroSimples('pagou');
        $origem = $this->filtroSimples('origem');
        $busca = $this->getTableSearch();

        return array_values(array_filter(
            $this->relatorio()->linhas,
            static fn (LinhaDoRelatorio $linha): bool => ($rubricas === [] || in_array($linha->lancamento->rubrica()->value, $rubricas, true))
                && ($pagou === null || $linha->lancamento->whoPaid()->value === $pagou)
                && ($origem === null || ($origem === 'cartao') === $linha->lancamento->ehDeFatura())
                && ($busca === null || Str::contains($linha->lancamento->description(), $busca, ignoreCase: true)),
        ));
    }

    private function filtroSimples(string $nome): ?string
    {
        $valor = $this->tableFilters[$nome]['value'] ?? null;

        return ($valor === null || $valor === '') ? null : (string) $valor;
    }

    /**
     * No lugar do rodapé de totais, que tabela com dados próprios não tem.
     */
    private function resumoDasLinhasFiltradas(): string
    {
        $linhas = $this->linhasFiltradas();
        $total = Money::zero();
        $semForaDoRateio = Money::zero();

        foreach ($linhas as $linha) {
            $total = $total->somar($linha->lancamento->amount());

            if ($linha->lancamento->rubrica()->contaComoGasto()) {
                $semForaDoRateio = $semForaDoRateio->somar($linha->lancamento->amount());
            }
        }

        return sprintf(
            '%d lançamento(s) · %s · sem os fora do rateio: %s. Filtros e busca valem só para esta tabela — as seções acima são sempre do mês inteiro.',
            count($linhas),
            $this->formatMoney($total),
            $this->formatMoney($semForaDoRateio),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paraLinhaDaTabela(LinhaDoRelatorio $linha): array
    {
        $lancamento = $linha->lancamento;
        $rubrica = $lancamento->rubrica();
        $individual = $rubrica->contaComoGasto() && ! $rubrica->entraNoRateio();

        return [
            'key' => $linha->transactionId,
            'fatura_id' => $linha->faturaId,
            'data' => $linha->dataDaCompra->format('d/m/Y'),
            'descricao' => $lancamento->description(),
            'parcela' => $linha->parcela === null ? null : 'Parcela ' . $linha->parcela,
            'origem' => $linha->fatura ?? ('Avulso' . ($linha->origem === null ? '' : ' · ' . $linha->origem)),
            'categoria' => $linha->categoria,
            'pagou' => $lancamento->whoPaid()->value,
            // Só individual tem responsável: comum é de todos, e o que está
            // fora do rateio não é despesa de ninguém.
            'responsavel' => $individual ? $lancamento->responsavelEfetivo()->value : '—',
            'responsavel_assumido' => $individual && $lancamento->responsible() === null,
            'rubrica' => $rubrica->value,
            'valor' => $this->formatMoney($lancamento->amount()),
            'estorno' => $lancamento->ehCredito(),
            'fora_do_rateio' => ! $rubrica->contaComoGasto(),
        ];
    }

    /**
     * Linha fora do rateio fica esmaecida, como a série quitada em Parcelas.
     *
     * @param  array<string, mixed>  $record
     */
    private function corDaLinha(array $record): ?string
    {
        return $record['fora_do_rateio'] ? 'gray' : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mes_anterior')
                ->label('Mês anterior')
                ->icon('heroicon-o-chevron-left')
                ->color('gray')
                ->action(fn () => $this->mes = $this->competencia()->mesAnterior()->chave()),

            Action::make('proximo_mes')
                ->label('Próximo mês')
                ->icon('heroicon-o-chevron-right')
                ->iconPosition('after')
                ->color('gray')
                ->action(fn () => $this->mes = $this->competencia()->proximoMes()->chave()),

            Action::make('abrir_fechamento')
                ->label('Abrir fechamento')
                ->icon('heroicon-o-calculator')
                ->color('gray')
                ->url(fn (): ?string => $this->relatorio()->persistido === null
                    ? null
                    : MonthlySummaryResource::getUrl('view', ['record' => $this->relatorio()->persistido]))
                ->visible(fn (): bool => $this->relatorio()->persistido !== null),

            // Mesmo serviço do "Calcular mês" do Consolidado, já com a
            // competência da página. Chama a atenção quando o salvo está velho.
            Action::make('recalcular')
                ->label(fn (): string => $this->relatorio()->persistido === null ? 'Calcular fechamento' : 'Recalcular fechamento')
                ->icon('heroicon-o-arrow-path')
                ->color(fn (): string => $this->relatorio()->divergeDoPersistido() ? 'warning' : 'gray')
                ->action(fn () => $this->recalcularFechamento()),

            Action::make('exportar')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): BinaryFileResponse => $this->baixarPlanilha()),
        ];
    }

    private function secaoResumo(): Section
    {
        return Section::make('Resumo')
            ->description('Calculado agora, direto das transações da competência.')
            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->schema([
                TextEntry::make('resumo_total_gasto')
                    ->label('Total gasto')
                    ->weight(FontWeight::SemiBold)
                    ->state(fn (): string => $this->formatMoney($this->relatorio()->totalGasto()))
                    ->helperText('comuns + individuais'),

                TextEntry::make('resumo_total_comum')
                    ->label('Total comum')
                    ->state(fn (): string => $this->formatMoney($this->relatorio()->fechamento->totalComum()))
                    ->helperText('o que é rateado'),

                TextEntry::make('resumo_acerto')
                    ->label('Acerto')
                    ->badge()
                    ->color(fn (): string => $this->relatorio()->fechamento->acertos() === [] ? 'success' : 'warning')
                    ->state(fn (): string => $this->fraseDoAcerto()),

                TextEntry::make('resumo_fechamento')
                    ->label('Fechamento salvo')
                    ->state(fn (): string => $this->situacaoDoFechamentoSalvo())
                    ->helperText(fn (): ?string => match ($this->relatorio()->persistido?->difference_paid) {
                        null => null,
                        true => 'acerto já pago',
                        false => 'acerto em aberto',
                    }),

                TextEntry::make('resumo_divergencia')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->color('warning')
                    ->weight(FontWeight::SemiBold)
                    ->icon('heroicon-o-exclamation-triangle')
                    ->state('O fechamento salvo está desatualizado: as transações mudaram depois do último cálculo. Use "Recalcular fechamento".')
                    ->visible(fn (): bool => $this->relatorio()->divergeDoPersistido()),

                TextEntry::make('resumo_fora_do_rateio')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->color('gray')
                    ->state(fn (): string => sprintf(
                        '%d lançamento(s) fora do rateio, somando %s — pagamento de fatura não é despesa e não entra em conta nenhuma.',
                        count($this->relatorio()->linhasDa(Rubrica::ForaDoRateio)),
                        $this->formatMoney($this->relatorio()->totalDa(Rubrica::ForaDoRateio)),
                    ))
                    ->visible(fn (): bool => $this->relatorio()->linhasDa(Rubrica::ForaDoRateio) !== []),
            ]);
    }

    private function secaoRateio(): Section
    {
        return Section::make('Passo 1 — Rateio das despesas comuns')
            ->description(fn (): string => sprintf(
                'Total comum de %s ÷ %d = cota de cada um. Quem pagou mais que a própria cota tem a diferença a receber; quem pagou menos, a pagar.',
                $this->formatMoney($this->relatorio()->fechamento->totalComum()),
                count(Participant::cases()),
            ))
            ->schema([
                RepeatableEntry::make('rateio')
                    ->hiddenLabel()
                    ->state(fn (): array => $this->porParticipante(fn (FechamentoDoParticipante $p): array => [
                        'participante' => $p->participante->value,
                        'pago' => $this->formatMoney($p->pagoComum),
                        'cota' => $this->formatMoney($p->cotaComum),
                        'diferenca' => $this->formatMoney($p->pagoComum->subtrair($p->cotaComum)),
                    ]))
                    ->table([
                        TableColumn::make('Participante'),
                        TableColumn::make('Pagou de comum')->alignment(Alignment::End),
                        TableColumn::make('Cota')->alignment(Alignment::End),
                        TableColumn::make('Diferença (pagou − cota)')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('participante'),
                        TextEntry::make('pago')->alignEnd(),
                        TextEntry::make('cota')->alignEnd(),
                        TextEntry::make('diferenca')->alignEnd()->weight(FontWeight::SemiBold),
                    ]),

                TextEntry::make('rateio_resto')
                    ->hiddenLabel()
                    ->color('gray')
                    ->state(fn (): string => sprintf(
                        'O total comum não divide exato: o centavo que sobra da divisão fica na cota de %s.',
                        $this->quemFicaComOResto()?->value,
                    ))
                    ->visible(fn (): bool => $this->quemFicaComOResto() !== null),
            ]);
    }

    private function secaoDividasCruzadas(): Section
    {
        return Section::make('Passo 2 — Dívidas cruzadas')
            ->description('Despesa individual paga por quem não é o responsável. Não entra no rateio: quem pagou tem o valor inteiro a receber do responsável.')
            ->schema([
                RepeatableEntry::make('cruzadas')
                    ->hiddenLabel()
                    ->state(fn (): array => array_map(
                        fn (LinhaDoRelatorio $linha): array => [
                            'descricao' => $linha->lancamento->description(),
                            'pagou' => $linha->lancamento->whoPaid()->value,
                            'responsavel' => $linha->lancamento->responsavelEfetivo()->value,
                            'valor' => $this->formatMoney($linha->lancamento->amount()),
                        ],
                        $this->relatorio()->linhasDa(Rubrica::DividaCruzada),
                    ))
                    ->placeholder('Nenhuma dívida cruzada nesta competência.')
                    ->table([
                        TableColumn::make('Despesa'),
                        TableColumn::make('Quem pagou'),
                        TableColumn::make('Responsável'),
                        TableColumn::make('Valor')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('descricao'),
                        TextEntry::make('pagou'),
                        TextEntry::make('responsavel'),
                        TextEntry::make('valor')->alignEnd(),
                    ]),

                RepeatableEntry::make('ajustes')
                    ->label('Ajuste líquido')
                    ->state(fn (): array => $this->porParticipante(fn (FechamentoDoParticipante $p): array => [
                        'participante' => $p->participante->value,
                        'ajuste' => $this->formatMoney($p->ajusteDividaCruzada),
                    ]))
                    ->table([
                        TableColumn::make('Participante'),
                        TableColumn::make('Adiantou pelo outro − foi adiantado')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('participante'),
                        TextEntry::make('ajuste')->alignEnd()->weight(FontWeight::SemiBold),
                    ])
                    ->visible(fn (): bool => $this->relatorio()->linhasDa(Rubrica::DividaCruzada) !== []),
            ]);
    }

    private function secaoSaldoEAcerto(): Section
    {
        return Section::make('Passo 3 — Saldo e acerto')
            ->description('Saldo = diferença do rateio + ajuste das dívidas cruzadas. Positivo é a receber, negativo é a pagar — e um é o espelho do outro.')
            ->schema([
                RepeatableEntry::make('saldos')
                    ->hiddenLabel()
                    ->state(fn (): array => $this->porParticipante(fn (FechamentoDoParticipante $p): array => [
                        'participante' => $p->participante->value,
                        'diferenca' => $this->formatMoney($p->pagoComum->subtrair($p->cotaComum)),
                        'ajuste' => $this->formatMoney($p->ajusteDividaCruzada),
                        'saldo' => $this->formatMoney($p->saldo),
                    ]))
                    ->table([
                        TableColumn::make('Participante'),
                        TableColumn::make('Diferença do rateio')->alignment(Alignment::End),
                        TableColumn::make('Ajuste cruzado')->alignment(Alignment::End),
                        TableColumn::make('Saldo')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('participante'),
                        TextEntry::make('diferenca')->alignEnd(),
                        TextEntry::make('ajuste')->alignEnd(),
                        TextEntry::make('saldo')->alignEnd()->weight(FontWeight::SemiBold),
                    ]),

                TextEntry::make('acerto')
                    ->label('Acerto')
                    ->inlineLabel()
                    ->weight(FontWeight::SemiBold)
                    ->state(fn (): string => $this->fraseDoAcerto()),

                $this->conferencia('conferencia_saldos', fn (): Conferencia => $this->relatorio()->conferenciaDosSaldos()),
            ]);
    }

    private function secaoCustoDeVida(): Section
    {
        return Section::make('Custo de vida')
            ->description('Quanto a vida de cada um custou: a cota do comum mais as individuais de sua responsabilidade, não importa quem pagou. É outra pergunta, diferente do saldo — os dois não se somam.')
            ->schema([
                RepeatableEntry::make('custo_de_vida')
                    ->hiddenLabel()
                    ->state(fn (): array => $this->porParticipante(fn (FechamentoDoParticipante $p): array => [
                        'participante' => $p->participante->value,
                        'cota' => $this->formatMoney($p->cotaComum),
                        'individuais' => $this->formatMoney($this->relatorio()->individuaisDe($p->participante)),
                        'custo' => $this->formatMoney($p->custoDeVida),
                    ]))
                    ->table([
                        TableColumn::make('Participante'),
                        TableColumn::make('Cota do comum')->alignment(Alignment::End),
                        TableColumn::make('Individuais de sua responsabilidade')->alignment(Alignment::End),
                        TableColumn::make('Custo de vida')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('participante'),
                        TextEntry::make('cota')->alignEnd(),
                        TextEntry::make('individuais')->alignEnd(),
                        TextEntry::make('custo')->alignEnd()->weight(FontWeight::SemiBold),
                    ]),

                $this->conferencia('conferencia_custo_de_vida', fn (): Conferencia => $this->relatorio()->conferenciaDoCustoDeVida()),
            ]);
    }

    private function secaoOrigem(): Section
    {
        return Section::make('Origem dos gastos')
            ->description('Por onde o dinheiro saiu: faturas de cartão, despesas da casa (comuns fora de cartão) e individuais avulsas. "Cartão de D" são os itens de fatura pagos por D.')
            ->schema([
                RepeatableEntry::make('cartoes')
                    ->label('Cartões')
                    ->state(fn (): array => $this->porParticipante(fn (FechamentoDoParticipante $p): array => [
                        'participante' => 'Cartão de ' . $p->participante->value,
                        'total' => $this->formatMoney($p->cartaoTotal),
                        'comum' => $this->formatMoney($p->cartaoComum),
                        'individual' => $this->formatMoney($p->cartaoIndividual),
                    ]))
                    ->table([
                        TableColumn::make('Cartão'),
                        TableColumn::make('Total')->alignment(Alignment::End),
                        TableColumn::make('Comum')->alignment(Alignment::End),
                        TableColumn::make('Individual')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('participante'),
                        TextEntry::make('total')->alignEnd()->weight(FontWeight::SemiBold),
                        TextEntry::make('comum')->alignEnd(),
                        TextEntry::make('individual')->alignEnd(),
                    ]),

                RepeatableEntry::make('casa')
                    ->label('Despesas da casa')
                    ->state(fn (): array => array_map(
                        fn (DespesaDaCasa $despesa): array => [
                            'label' => $despesa->label,
                            'valor' => $this->formatMoney($despesa->valor),
                        ],
                        $this->relatorio()->fechamento->despesasDaCasa(),
                    ))
                    ->placeholder('Nenhuma despesa da casa nesta competência.')
                    ->table([
                        TableColumn::make('Descrição'),
                        TableColumn::make('Valor')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('label'),
                        TextEntry::make('valor')->alignEnd(),
                    ]),

                TextEntry::make('origem_total_casa')
                    ->label('Total da casa')
                    ->inlineLabel()
                    ->weight(FontWeight::SemiBold)
                    ->state(fn (): string => $this->formatMoney($this->relatorio()->fechamento->totalCasa())),

                TextEntry::make('origem_individuais_avulsas')
                    ->label('Individuais avulsas')
                    ->inlineLabel()
                    ->helperText('individuais fora de cartão: não são da casa nem de fatura')
                    ->state(fn (): string => $this->formatMoney($this->relatorio()->individuaisAvulsas())),

                $this->conferencia('conferencia_total_comum', fn (): Conferencia => $this->relatorio()->conferenciaDoTotalComum()),

                $this->conferencia('conferencia_origem', fn (): Conferencia => $this->relatorio()->conferenciaDaOrigem()),
            ]);
    }

    /**
     * Uma conferência do relatório: os dois lados da igualdade chegam por
     * caminhos diferentes. Se não fechar, aparece em vermelho com os dois
     * valores — é exatamente o que esta página existe para mostrar.
     *
     * @param  Closure(): Conferencia  $conferencia
     */
    private function conferencia(string $nome, Closure $conferencia): TextEntry
    {
        return TextEntry::make($nome)
            ->label('Conferência')
            ->inlineLabel()
            ->icon(static fn (): string => $conferencia()->fecha() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color(static fn (): string => $conferencia()->fecha() ? 'success' : 'danger')
            ->state(function () use ($conferencia): string {
                $c = $conferencia();

                return sprintf(
                    '%s: %s %s %s',
                    $c->regra,
                    $this->formatMoney($c->esquerda),
                    $c->fecha() ? '=' : '≠',
                    $this->formatMoney($c->direita),
                );
            });
    }

    private function recalcularFechamento(): void
    {
        try {
            $this->fechamento->apurarEPersistir($this->competencia());

            // O relatório em cache ainda aponta para o fechamento antigo.
            $this->relatorio = null;

            Notification::make()
                ->title('Fechamento de ' . $this->formatCompetencia($this->competencia()) . ' salvo')
                ->body($this->fraseDoAcerto())
                ->success()
                ->send();
        } catch (Throwable $erro) {
            report($erro); // o detalhe vai para o log, não para a tela

            Notification::make()
                ->title('Não foi possível calcular')
                ->body($erro->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * A planilha sai do mesmo relatório da tela, inteiro: filtros e busca da
     * tabela não valem para a exportação.
     */
    private function baixarPlanilha(): BinaryFileResponse
    {
        $caminho = tempnam(sys_get_temp_dir(), 'relatorio-');

        $this->exportador->gravar($this->relatorio(), $caminho);

        return response()
            ->download($caminho, sprintf('relatorio-%s.xlsx', $this->competencia()->chave()))
            ->deleteFileAfterSend();
    }

    private function relatorio(): Relatorio
    {
        $chave = $this->competencia()->chave();

        if ($this->relatorio === null || $this->mesDoRelatorio !== $chave) {
            $this->relatorio = $this->servico->gerar($this->competencia());
            $this->mesDoRelatorio = $chave;
        }

        return $this->relatorio;
    }

    /**
     * @param  callable(FechamentoDoParticipante): array<string, string>  $paraLinha
     * @return list<array<string, string>>
     */
    private function porParticipante(callable $paraLinha): array
    {
        return array_map($paraLinha, $this->relatorio()->fechamento->participantes());
    }

    private function fraseDoAcerto(): string
    {
        $acerto = $this->relatorio()->fechamento->acertos()[0] ?? null;

        return $acerto === null
            ? 'Sem acerto'
            : sprintf('%s paga %s a %s', $acerto->paga->value, $this->formatMoney($acerto->valor), $acerto->recebe->value);
    }

    private function situacaoDoFechamentoSalvo(): string
    {
        $salvo = $this->relatorio()->persistido;

        if ($salvo === null) {
            return 'Ainda não fechado';
        }

        return $salvo->calculated_at === null
            ? 'Fechado'
            : 'Fechado em ' . $salvo->calculated_at->format('d/m/Y');
    }

    /**
     * Quando o total comum não divide exato, `Money::dividirIgualmente()` dá
     * o centavo de resto às primeiras partes — aqui, quem ficou com a cota
     * maior em valor absoluto. Null quando as cotas são iguais.
     */
    private function quemFicaComOResto(): ?Participant
    {
        $participantes = $this->relatorio()->fechamento->participantes();
        $menor = min(array_map(
            static fn (FechamentoDoParticipante $p): int => $p->cotaComum->absoluto()->centavos(),
            $participantes,
        ));

        foreach ($participantes as $participante) {
            if ($participante->cotaComum->absoluto()->centavos() > $menor) {
                return $participante->participante;
            }
        }

        return null;
    }

    private function competencia(): ReferenceMonth
    {
        return $this->mesValido($this->mes);
    }

    /** Valor vazio ou inválido (ex.: URL editada à mão) cai no mês atual. */
    private function mesValido(string $mes): ReferenceMonth
    {
        try {
            return ReferenceMonth::fromString($mes);
        } catch (CompetenciaInvalida) {
            return ReferenceMonth::fromDate(new DateTimeImmutable('today'));
        }
    }

    /**
     * Últimos 24 meses e os 3 próximos — mais a competência corrente da
     * página, caso a navegação tenha saído dessa janela.
     *
     * @return array<string, string>
     */
    private function opcoesDeMes(): array
    {
        $opcoes = [];
        $inicio = Carbon::now()->startOfMonth()->addMonths(3);

        for ($i = 0; $i < 27; $i++) {
            $data = $inicio->copy()->subMonths($i);
            $opcoes[$data->format('Y-m')] = $data->translatedFormat('M/Y');
        }

        $atual = $this->competencia();
        $opcoes[$atual->chave()] ??= $this->formatCompetencia($atual);

        return $opcoes;
    }
}
