<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\DespesaDaCasa;
use App\Domain\Finance\ValueObjects\FechamentoDoParticipante;
use App\Domain\Finance\ValueObjects\FechamentoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

/**
 * Apura de uma vez tudo o que o fechamento de um mês precisa.
 *
 * É um compositor: o rateio e o acerto vêm de {@see CalculadoraDeSaldoMensal}
 * e o custo de vida de {@see CalculadoraDeCustoDeVida} — aqui só se somam os
 * totais que faltam (casa e cartões) e se junta tudo num
 * {@see FechamentoMensal}.
 */
final class ApuradorDeFechamentoMensal
{
    public function __construct(
        private readonly CalculadoraDeSaldoMensal $calculadoraDeSaldo = new CalculadoraDeSaldoMensal(),
        private readonly CalculadoraDeCustoDeVida $calculadoraDeCustoDeVida = new CalculadoraDeCustoDeVida(),
    ) {
    }

    /**
     * @param  list<Transaction>  $lancamentos  Lançamentos de qualquer período — o filtro por competência é feito aqui.
     * @param  list<Participant>  $participantes
     */
    public function apurar(ReferenceMonth $mes, array $lancamentos, array $participantes): FechamentoMensal
    {
        $participantes = array_values($participantes);

        $saldoMensal = $this->calculadoraDeSaldo->calcular($mes, $lancamentos, $participantes);
        $custoDeVida = $this->calculadoraDeCustoDeVida->calcular($mes, $lancamentos, $participantes);

        $relevantes = array_filter(
            $lancamentos,
            static fn (Transaction $lancamento): bool => $lancamento->contaParaBalanco()
                && $lancamento->referenceMonth()->equals($mes),
        );

        $totalComum = Money::zero();
        $totalCasa = Money::zero();
        $casaPorLabel = [];
        [$cartaoTotal, $cartaoComum, $cartaoIndividual] = $this->cartoesZerados($participantes);

        foreach ($relevantes as $lancamento) {
            if ($lancamento->ehDespesaComum()) {
                $totalComum = $totalComum->somar($lancamento->amount());
            }

            if ($lancamento->ehDeFatura()) {
                $dono = $lancamento->whoPaid()->value;
                $cartaoTotal[$dono] = ($cartaoTotal[$dono] ?? Money::zero())->somar($lancamento->amount());

                if ($lancamento->ehDespesaComum()) {
                    $cartaoComum[$dono] = ($cartaoComum[$dono] ?? Money::zero())->somar($lancamento->amount());
                } else {
                    $cartaoIndividual[$dono] = ($cartaoIndividual[$dono] ?? Money::zero())->somar($lancamento->amount());
                }

                continue;
            }

            // Casa: despesa comum que não veio de fatura. Individual avulsa
            // não é despesa da casa — é gasto pessoal de alguém.
            if ($lancamento->ehDespesaComum()) {
                $totalCasa = $totalCasa->somar($lancamento->amount());

                $label = $lancamento->description();
                $casaPorLabel[$label] = ($casaPorLabel[$label] ?? Money::zero())->somar($lancamento->amount());
            }
        }

        $fechamentos = [];
        foreach ($participantes as $participante) {
            $chave = $participante->value;

            $fechamentos[$chave] = new FechamentoDoParticipante(
                participante: $participante,
                cartaoTotal: $cartaoTotal[$chave],
                cartaoComum: $cartaoComum[$chave],
                cartaoIndividual: $cartaoIndividual[$chave],
                pagoComum: $saldoMensal->totalPagoComumDe($participante),
                cotaComum: $saldoMensal->cotaComumDe($participante),
                ajusteDividaCruzada: $saldoMensal->ajusteDeDividaCruzadaDe($participante),
                saldo: $saldoMensal->saldoDe($participante),
                custoDeVida: $custoDeVida[$chave] ?? Money::zero(),
            );
        }

        return new FechamentoMensal(
            mes: $mes,
            totalComum: $totalComum,
            totalCasa: $totalCasa,
            despesasDaCasa: $this->paraLinhasDaCasa($casaPorLabel),
            participantes: $fechamentos,
            saldoMensal: $saldoMensal,
        );
    }

    /**
     * @param  list<Participant>  $participantes
     * @return array{array<string, Money>, array<string, Money>, array<string, Money>}
     */
    private function cartoesZerados(array $participantes): array
    {
        $zerado = [];
        foreach ($participantes as $participante) {
            $zerado[$participante->value] = Money::zero();
        }

        return [$zerado, $zerado, $zerado];
    }

    /**
     * @param  array<string, Money>  $casaPorLabel
     * @return list<DespesaDaCasa>
     */
    private function paraLinhasDaCasa(array $casaPorLabel): array
    {
        $linhas = [];
        foreach ($casaPorLabel as $label => $valor) {
            $linhas[] = new DespesaDaCasa((string) $label, $valor);
        }

        return $linhas;
    }
}
