<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Exceptions\CompetenciaInvalida;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Filament\Concerns\FormataValores;
use App\Filament\Pages\Widgets\LancamentosDaFatura;
use BackedEnum;
use DateTimeImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * As faturas de cartão de D e J de um mês de vencimento, lado a lado.
 *
 * A página guarda só o mês (string `Y-m`, na URL); cada fatura é um
 * {@see LancamentosDaFatura}, recriado quando o mês muda. Nenhum model em
 * propriedade pública — o Livewire os recarregaria a cada request, sem a
 * ordenação da consulta original.
 *
 * O acesso é controlado pelo painel, não por autorização própria da página.
 */
class BillsSideBySideOverview extends Page
{
    use FormataValores;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $title = 'Faturas Mensais';

    protected static string|UnitEnum|null $navigationGroup = 'Resultados/Relatórios';

    protected static ?int $navigationSort = 4;

    /** Mês de vencimento, `Y-m`. Vai na URL: o link abre no mesmo mês. */
    #[Url]
    public string $mes = '';

    public function mount(): void
    {
        $this->mes = $this->mesValido($this->mes)->chave();
    }

    /** Escolha no select: normaliza antes de os widgets lerem. */
    public function updatedMes(): void
    {
        $this->mes = $this->mesValido($this->mes)->chave();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Vencimento em ' . $this->formatCompetencia($this->competencia());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('mes')
                ->label('Mês de vencimento')
                ->options($this->opcoesDeMes())
                ->selectablePlaceholder(false)
                ->live()
                ->maxWidth('xs'),

            Grid::make(['default' => 1, 'lg' => 2])
                ->schema(array_map(
                    fn (Participant $dono): Livewire => Livewire::make(LancamentosDaFatura::class, fn (): array => [
                        'dono' => $dono->value,
                        'mes' => $this->mes,
                    ])
                        // Chave com o mês: trocar o mês recria o widget, que
                        // então consulta a fatura nova.
                        ->key(fn (): string => "fatura-$dono->value-$this->mes"),
                    Participant::cases(),
                )),
        ]);
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
        ];
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
     * Últimos 24 meses e os 3 próximos — mais o mês corrente da página, caso
     * a navegação tenha saído dessa janela.
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
