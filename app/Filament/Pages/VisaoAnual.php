<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Filament\Concerns\FormataValores;
use App\Infra\Csv\ConversorDeValorBrasileiro;
use App\Models\MonthlyParticipantSummary;
use App\Models\MonthlySummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * O ano mês a mês: fatura (total, comum, individual) e custo de vida de cada
 * participante, lidos **só do fechamento persistido** — nada é apurado ao
 * vivo. Mês sem fechamento aparece marcado, não some.
 *
 * "Mês" é a competência (mês do vencimento), como no resto do sistema.
 *
 * O acesso é controlado pelo painel, não por autorização própria da página.
 */
class VisaoAnual extends Page implements HasTable
{
    use FormataValores;
    use InteractsWithTable;

    /** Os campos de `monthly_participant_summaries` exibidos, na ordem das colunas. */
    private const array CAMPOS = [
        'card_total' => 'Fatura',
        'card_common' => 'Comum',
        'card_individual' => 'Individual',
        'living_cost' => 'Custo de vida',
    ];

    private const string CHAVE_DO_TOTAL = 'total';

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $title = 'Visão Anual';

    protected static string|UnitEnum|null $navigationGroup = 'Resultados/Relatórios';

    protected static ?int $navigationSort = 5;

    /**
     * Ano das competências. String (e não int) para que um valor inválido
     * vindo da URL seja normalizado em vez de quebrar a hidratação.
     */
    #[Url]
    public string $ano = '';

    /**
     * Cache por request (o Livewire não serializa privadas), guardado junto
     * com o ano: uma action troca o ano depois de algo já ter lido.
     *
     * @var Collection<int, MonthlySummary>|null
     */
    private ?Collection $fechamentos = null;

    private ?int $anoDosFechamentos = null;

    public function mount(): void
    {
        $this->ano = (string) $this->anoValido($this->ano);
    }

    /** Escolha no select: normaliza antes de qualquer leitura. */
    public function updatedAno(): void
    {
        $this->ano = (string) $this->anoValido($this->ano);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Fechamentos salvos de ' . $this->anoAtual() . ' — cada mês é a competência (mês do vencimento).';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('ano')
                ->label('Ano')
                ->options($this->opcoesDeAno())
                ->selectablePlaceholder(false)
                ->live()
                ->maxWidth('xs'),

            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => 'Competências de ' . $this->anoAtual())
            ->description(fn (): string => $this->resumoDoAno())
            ->records(fn (): array => $this->linhas())
            ->paginated(false)
            ->columns([
                TextColumn::make('mes')
                    ->label('Competência')
                    ->weight(fn (array $record): ?FontWeight => $record['total'] ? FontWeight::Bold : null)
                    ->description(fn (array $record): ?string => $record['gastos_de'])
                    ->badge(fn (array $record): bool => ! $record['fechado'])
                    ->color(fn (array $record): ?string => $record['fechado'] ? null : 'warning')
                    ->tooltip(fn (array $record): ?string => $record['fechado']
                        ? null
                        : 'Competência não calculada — use "Calcular mês" no Consolidado Mensal'),
                ...array_map($this->grupoDoParticipante(...), Participant::cases()),
            ])
            ->recordActions([
                Action::make('relatorio')
                    ->label('Relatório')
                    ->hiddenLabel()
                    ->tooltip('Relatório detalhado: de onde sai cada número')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->visible(fn (array $record): bool => $record['fechado'] && ! $record['total'])
                    ->url(fn (array $record): string => RelatorioDaCompetencia::getUrl(['mes' => $record['key']])),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ano_anterior')
                ->label('Ano anterior')
                ->icon('heroicon-o-chevron-left')
                ->color('gray')
                ->action(fn () => $this->ano = (string) ($this->anoAtual() - 1)),

            Action::make('proximo_ano')
                ->label('Próximo ano')
                ->icon('heroicon-o-chevron-right')
                ->iconPosition('after')
                ->color('gray')
                ->action(fn () => $this->ano = (string) ($this->anoAtual() + 1)),
        ];
    }

    private function grupoDoParticipante(Participant $participante): ColumnGroup
    {
        $colunas = [];

        foreach (self::CAMPOS as $campo => $rotulo) {
            $colunas[] = TextColumn::make("{$participante->value}_$campo")
                ->label($rotulo)
                ->alignEnd()
                ->placeholder('—')
                ->weight(fn (array $record): ?FontWeight => $record['total'] ? FontWeight::Bold : null)
                ->color($campo === 'living_cost' ? 'success' : null);
        }

        return ColumnGroup::make($participante->value, $colunas)->alignCenter();
    }

    /**
     * Os 12 meses do ano, sempre todos, mais a linha de total (só dos meses
     * fechados). Tabela com `->records()` não tem summarizer.
     *
     * @return array<string, array<string, mixed>>
     */
    private function linhas(): array
    {
        $fechamentos = $this->fechamentos();
        $ano = $this->anoAtual();
        $totais = [];
        $linhas = [];

        for ($numero = 1; $numero <= 12; $numero++) {
            $competencia = ReferenceMonth::fromString(sprintf('%04d-%02d', $ano, $numero));
            $fechamento = $fechamentos->get($numero);

            $linha = [
                'key' => $competencia->chave(),
                'mes' => $this->formatCompetencia($competencia),
                'gastos_de' => $fechamento === null
                    ? 'Sem fechamento'
                    : 'gastos de ' . $this->formatCompetencia($competencia->mesAnterior()),
                'fechado' => $fechamento !== null,
                'total' => false,
            ];

            foreach (Participant::cases() as $participante) {
                // Fechamento sem a linha do participante: só as colunas dele ficam vazias.
                $resumo = $fechamento?->participantSummary($participante);

                foreach (array_keys(self::CAMPOS) as $campo) {
                    $chave = "{$participante->value}_$campo";
                    $valor = $this->emDinheiro($resumo, $campo);

                    $linha[$chave] = $valor === null ? null : $this->formatMoney($valor);

                    if ($valor !== null) {
                        $totais[$chave] = ($totais[$chave] ?? Money::zero())->somar($valor);
                    }
                }
            }

            $linhas[$competencia->chave()] = $linha;
        }

        $linhaDoTotal = [
            'key' => self::CHAVE_DO_TOTAL,
            'mes' => 'Total do ano',
            'gastos_de' => 'só meses fechados',
            'fechado' => true,
            'total' => true,
        ];

        foreach (Participant::cases() as $participante) {
            foreach (array_keys(self::CAMPOS) as $campo) {
                $chave = "{$participante->value}_$campo";
                $linhaDoTotal[$chave] = isset($totais[$chave]) ? $this->formatMoney($totais[$chave]) : null;
            }
        }

        $linhas[self::CHAVE_DO_TOTAL] = $linhaDoTotal;

        return $linhas;
    }

    private function resumoDoAno(): string
    {
        $ano = $this->anoAtual();
        $fechados = $this->fechamentos()->keys();

        if ($fechados->isEmpty()) {
            return "Nenhuma competência fechada em $ano.";
        }

        $faltantes = collect(range(1, 12))
            ->diff($fechados)
            ->map(fn (int $numero): string => sprintf('%02d', $numero))
            ->values();

        $resumo = sprintf('%d de 12 competências fechadas', $fechados->count());

        return $faltantes->isEmpty()
            ? "$resumo."
            : "$resumo — sem fechamento: " . $faltantes->implode(', ') . '.';
    }

    /**
     * Os fechamentos do ano, indexados pelo número do mês. Uma consulta só,
     * com as linhas dos participantes já carregadas.
     *
     * @return Collection<int, MonthlySummary>
     */
    private function fechamentos(): Collection
    {
        $ano = $this->anoAtual();

        if ($this->fechamentos === null || $this->anoDosFechamentos !== $ano) {
            $this->fechamentos = MonthlySummary::query()
                ->with('participantSummaries')
                ->whereYear('competencia', $ano)
                ->get()
                ->keyBy(static fn (MonthlySummary $resumo): int => (int) $resumo->competencia->format('n'));
            $this->anoDosFechamentos = $ano;
        }

        return $this->fechamentos;
    }

    /** O `decimal:2` do banco em `Money`, sem passar por float. */
    private function emDinheiro(?MonthlyParticipantSummary $resumo, string $campo): ?Money
    {
        if ($resumo === null) {
            return null;
        }

        return (new ConversorDeValorBrasileiro())->converter((string) ($resumo->{$campo} ?? '0'));
    }

    /**
     * Anos com fechamento, mais o corrente e o exibido (a navegação pode
     * sair dos anos com dados).
     *
     * @return array<string, string>
     */
    private function opcoesDeAno(): array
    {
        $anos = MonthlySummary::query()
            ->pluck('competencia')
            ->map(static fn ($competencia): int => (int) $competencia->format('Y'))
            ->push((int) date('Y'), $this->anoAtual())
            ->unique()
            ->sortDesc();

        return $anos
            ->mapWithKeys(static fn (int $ano): array => [(string) $ano => (string) $ano])
            ->all();
    }

    private function anoAtual(): int
    {
        return $this->anoValido($this->ano);
    }

    /** Vazio ou inválido (ex.: URL editada à mão) cai no ano corrente. */
    private function anoValido(string $ano): int
    {
        return preg_match('/^\d{4}$/', $ano) === 1 && (int) $ano >= 1900
            ? (int) $ano
            : (int) date('Y');
    }
}
