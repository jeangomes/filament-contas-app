<?php

declare(strict_types=1);

namespace App\Services\Relatorio;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\Rubrica;
use App\Domain\Finance\ValueObjects\FechamentoDoParticipante;
use App\Domain\Finance\ValueObjects\FechamentoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Infra\Csv\ConversorDeValorBrasileiro;
use App\Models\MonthlySummary;
use Closure;

/**
 * Tudo o que a tela e a planilha do relatório de uma competência mostram.
 *
 * Duas fontes convivem aqui, de propósito:
 * - os **resultados** (total comum, casa, cartões, cota, saldo, custo de
 *   vida, acerto) vêm sempre de {@see FechamentoMensal} — nunca de soma
 *   refeita;
 * - os métodos desta classe somam as **linhas**. Servem para os subtotais
 *   que o domínio não expõe e para as conferências, que comparam a soma das
 *   linhas com o número do domínio. É essa independência que dá sentido a
 *   uma conferência que fecha.
 *
 * Fica na camada de aplicação, não no domínio: carrega o fechamento
 * persistido (Eloquent) e dados só de exibição.
 */
final readonly class RelatorioDaCompetencia
{
    /**
     * @param  list<LinhaDoRelatorio>  $linhas
     */
    public function __construct(
        public ReferenceMonth   $mes,
        public FechamentoMensal $fechamento,
        public array            $linhas,
        public ?MonthlySummary  $persistido,
    ) {
    }

    /**
     * @return list<LinhaDoRelatorio>
     */
    public function linhasDa(Rubrica $rubrica): array
    {
        return array_values(array_filter(
            $this->linhas,
            static fn (LinhaDoRelatorio $linha): bool => $linha->lancamento->rubrica() === $rubrica,
        ));
    }

    public function totalDa(Rubrica $rubrica): Money
    {
        return $this->somar(
            static fn (LinhaDoRelatorio $linha): bool => $linha->lancamento->rubrica() === $rubrica,
        );
    }

    /**
     * Tudo o que foi despesa na competência — comuns e individuais, sem o
     * que ficou fora do rateio.
     */
    public function totalGasto(): Money
    {
        return $this->somar(
            static fn (LinhaDoRelatorio $linha): bool => $linha->lancamento->rubrica()->contaComoGasto(),
        );
    }

    /**
     * As individuais que pesam no custo de vida do participante: as de sua
     * responsabilidade, pagas por ele ou adiantadas pelo outro.
     */
    public function individuaisDe(Participant $participante): Money
    {
        return $this->somar(
            static fn (LinhaDoRelatorio $linha): bool => self::ehIndividual($linha)
                && $linha->lancamento->responsavelEfetivo() === $participante,
        );
    }

    /**
     * Individuais fora de fatura: não são "casa" (que é só o comum avulso)
     * nem cartão — sem elas a origem dos gastos não fecha com o total.
     */
    public function individuaisAvulsas(): Money
    {
        return $this->somar(
            static fn (LinhaDoRelatorio $linha): bool => self::ehIndividual($linha)
                && ! $linha->lancamento->ehDeFatura(),
        );
    }

    public function conferenciaDosSaldos(): Conferencia
    {
        return new Conferencia(
            'soma dos saldos = zero',
            $this->somarDosParticipantes(static fn (FechamentoDoParticipante $p): Money => $p->saldo),
            Money::zero(),
        );
    }

    public function conferenciaDoCustoDeVida(): Conferencia
    {
        return new Conferencia(
            'soma dos custos de vida = total gasto',
            $this->somarDosParticipantes(static fn (FechamentoDoParticipante $p): Money => $p->custoDeVida),
            $this->totalGasto(),
        );
    }

    public function conferenciaDoTotalComum(): Conferencia
    {
        return new Conferencia(
            'casa + comum dos cartões = total comum',
            $this->fechamento->totalCasa()
                ->somar($this->somarDosParticipantes(static fn (FechamentoDoParticipante $p): Money => $p->cartaoComum)),
            $this->fechamento->totalComum(),
        );
    }

    public function conferenciaDaOrigem(): Conferencia
    {
        return new Conferencia(
            'cartões + casa + individuais avulsas = total gasto',
            $this->somarDosParticipantes(static fn (FechamentoDoParticipante $p): Money => $p->cartaoTotal)
                ->somar($this->fechamento->totalCasa())
                ->somar($this->individuaisAvulsas()),
            $this->totalGasto(),
        );
    }

    /**
     * Todas as conferências, na ordem em que a tela as apresenta.
     *
     * @return list<Conferencia>
     */
    public function conferencias(): array
    {
        return [
            $this->conferenciaDosSaldos(),
            $this->conferenciaDoCustoDeVida(),
            $this->conferenciaDoTotalComum(),
            $this->conferenciaDaOrigem(),
        ];
    }

    /**
     * O fechamento salvo ainda reflete as transações? Sem fechamento salvo
     * não há o que divergir.
     */
    public function divergeDoPersistido(): bool
    {
        if ($this->persistido === null) {
            return false;
        }

        $acerto = $this->fechamento->acertos()[0] ?? null;

        if (! $this->confere($this->persistido->total_common_expenses, $this->fechamento->totalComum())
            || ! $this->confere($this->persistido->total_home_expenses, $this->fechamento->totalCasa())
            || ! $this->confere($this->persistido->settlement_amount, $acerto?->valor ?? Money::zero())
            || $this->persistido->settlement_payer !== $acerto?->paga
        ) {
            return true;
        }

        foreach ($this->fechamento->participantes() as $apurado) {
            $salvo = $this->persistido->participantSummary($apurado->participante);

            if ($salvo === null
                || ! $this->confere($salvo->balance, $apurado->saldo)
                || ! $this->confere($salvo->living_cost, $apurado->custoDeVida)
            ) {
                return true;
            }
        }

        return false;
    }

    private static function ehIndividual(LinhaDoRelatorio $linha): bool
    {
        $rubrica = $linha->lancamento->rubrica();

        return $rubrica->contaComoGasto() && ! $rubrica->entraNoRateio();
    }

    /**
     * @param  Closure(FechamentoDoParticipante): Money  $valor
     */
    private function somarDosParticipantes(Closure $valor): Money
    {
        $total = Money::zero();

        foreach ($this->fechamento->participantes() as $participante) {
            $total = $total->somar($valor($participante));
        }

        return $total;
    }

    /**
     * @param  Closure(LinhaDoRelatorio): bool  $filtro
     */
    private function somar(Closure $filtro): Money
    {
        $total = Money::zero();

        foreach ($this->linhas as $linha) {
            if ($filtro($linha)) {
                $total = $total->somar($linha->lancamento->amount());
            }
        }

        return $total;
    }

    /**
     * Compara em centavos: o decimal do banco vira `Money` por string, sem
     * passar por float.
     */
    private function confere(string|int|float|null $salvo, Money $apurado): bool
    {
        return (new ConversorDeValorBrasileiro())
            ->converter((string) ($salvo ?? '0'))
            ->equals($apurado);
    }
}
