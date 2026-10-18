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
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * As compras parceladas em curso e o que elas já comprometem dos meses à
 * frente.
 *
 * Tudo é derivado das transações pelo domínio ({@see ApuradorDeParcelamentos});
 * não há nada persistido aqui. Por isso a página monta a view à mão em vez de
 * usar uma tabela Filament, que espera uma query Eloquent.
 *
 * O acesso é controlado pelo painel, não por autorização própria da página.
 */
class ParcelasEmAndamento extends Page
{
    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-rectangle-stack';

    protected string $view = 'filament.pages.parcelas-em-andamento';

    protected static ?string $title = 'Parcelas';

    protected static string|UnitEnum|null $navigationGroup = 'Resultados/Relatórios';

    protected static ?int $navigationSort = 2;

    public bool $incluirQuitadas = false;

    /** Null = os dois participantes. */
    public ?string $pagador = null;

    private RepositorioDeLancamentos $repositorio;

    private ApuradorDeParcelamentos $apurador;

    public function boot(RepositorioDeLancamentos $repositorio, ApuradorDeParcelamentos $apurador): void
    {
        $this->repositorio = $repositorio;
        $this->apurador = $apurador;
    }

    /**
     * Todas as séries, já filtradas pelo que a tela pede. `#[Computed]` evita
     * repetir a apuração a cada leitura na view.
     *
     * @return list<Parcelamento>
     */
    #[Computed]
    public function parcelamentos(): array
    {
        $series = $this->apurador->apurar($this->repositorio->parcelados());

        // O select devolve '' para "Todos"; o filtro por pagador só existe
        // quando alguém foi escolhido de fato.
        $pagador = ($this->pagador === null || $this->pagador === '') ? null : $this->pagador;

        return array_values(array_filter(
            $series,
            fn (Parcelamento $serie): bool => ($this->incluirQuitadas || $serie->emAndamento())
                && ($pagador === null || $serie->quemPagou->value === $pagador),
        ));
    }

    /**
     * A projeção sai das séries **em andamento** já filtradas: o que a tabela
     * de cima mostra é o que o resumo soma.
     *
     * @return list<CompromissoMensal>
     */
    #[Computed]
    public function compromissos(): array
    {
        return $this->apurador->projetar($this->parcelamentos(), $this->competenciaAtual());
    }

    #[Computed]
    public function competenciaAtual(): ReferenceMonth
    {
        return ReferenceMonth::fromDate(new DateTimeImmutable('today'));
    }

    /**
     * Série com parcela a vencer cuja próxima já venceu: a fatura seguinte não
     * foi importada. Vale avisar em vez de mostrar "termina em 10/2025" como
     * se fosse compromisso.
     */
    public function estaDefasada(Parcelamento $serie): bool
    {
        return $serie->defasadaEm($this->competenciaAtual());
    }

    #[Computed]
    public function totalComprometido(): Money
    {
        $total = Money::zero();

        foreach ($this->compromissos() as $compromisso) {
            $total = $total->somar($compromisso->valor);
        }

        return $total;
    }

    /**
     * @return array<string, string>
     */
    public function opcoesDePagador(): array
    {
        $opcoes = ['' => 'Todos'];

        foreach (Participant::cases() as $participante) {
            $opcoes[$participante->value] = $participante->value;
        }

        return $opcoes;
    }

    /**
     * Formata a partir da representação decimal exata do `Money` — os
     * centavos nunca passam por float no caminho até a tela.
     */
    public function formatMoney(Money $valor): string
    {
        return 'R$ ' . number_format((float) $valor->paraDecimal(), 2, ',', '.');
    }

    public function formatCompetencia(ReferenceMonth $mes): string
    {
        return sprintf('%02d/%04d', $mes->numeroDoMes(), $mes->ano());
    }

    /**
     * Quanto da série já foi paga, em porcentagem — só para a barra de
     * progresso.
     */
    public function progresso(Parcelamento $serie): int
    {
        return (int) round($serie->parcelasPagas / $serie->totalDeParcelas * 100);
    }
}
