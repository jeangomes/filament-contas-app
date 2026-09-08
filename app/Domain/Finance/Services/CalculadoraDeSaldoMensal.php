<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\MonthlyBalance;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

/**
 * Regra central do sistema: apura o saldo do mês entre os participantes.
 *
 * 1. As despesas comuns do mês são somadas e rateadas em partes iguais —
 *    quem pagou mais que a própria cota fica com saldo positivo (a receber).
 * 2. Despesas individuais pagas por quem não é o responsável (dívida
 *    cruzada) creditam quem pagou e debitam o responsável.
 *
 * Serviço puro: não conhece banco, Eloquent nem SQL — recebe lançamentos já
 * materializados e devolve o saldo do mês.
 */
final class CalculadoraDeSaldoMensal
{
    /**
     * @param  list<Transaction>  $lancamentos  Lançamentos de qualquer período — o filtro por competência é feito aqui.
     * @param  list<Participant>  $participantes  Quem divide as despesas comuns (hoje, os dois cases de Participant).
     */
    public function calcular(ReferenceMonth $mes, array $lancamentos, array $participantes): MonthlyBalance
    {
        $participantes = array_values($participantes);

        $relevantes = array_values(array_filter(
            $lancamentos,
            static fn (Transaction $lancamento): bool => $lancamento->contaParaBalanco()
                && $lancamento->referenceMonth()->equals($mes),
        ));

        [$saldos, $totalPagoComum, $cotaComum] = $this->rateioDasDespesasComuns($relevantes, $participantes);
        $ajusteCruzado = $this->ajustesDeDividaCruzada($relevantes, $participantes);

        foreach ($ajusteCruzado as $chave => $ajuste) {
            $saldos[$chave] = ($saldos[$chave] ?? Money::zero())->somar($ajuste);
        }

        return new MonthlyBalance($mes, $saldos, $totalPagoComum, $cotaComum, $ajusteCruzado);
    }

    /**
     * Soma as despesas comuns por pagador e desconta a cota igualitária.
     *
     * @param  list<Transaction>  $lancamentos
     * @param  list<Participant>  $participantes
     * @return array{array<string, Money>, array<string, Money>, array<string, Money>} saldos, total pago e cota — por participante
     */
    private function rateioDasDespesasComuns(array $lancamentos, array $participantes): array
    {
        $pagoPorParticipante = [];
        foreach ($participantes as $participante) {
            $pagoPorParticipante[$participante->value] = Money::zero();
        }

        $totalComum = Money::zero();

        foreach ($lancamentos as $lancamento) {
            if (! $lancamento->ehDespesaComum()) {
                continue;
            }

            $pagador = $lancamento->whoPaid()->value;
            $totalComum = $totalComum->somar($lancamento->amount());
            $pagoPorParticipante[$pagador] = ($pagoPorParticipante[$pagador] ?? Money::zero())
                ->somar($lancamento->amount());
        }

        $cotas = $totalComum->dividirIgualmente(count($participantes));

        $saldos = [];
        $cotaPorParticipante = [];

        foreach ($participantes as $indice => $participante) {
            $cotaPorParticipante[$participante->value] = $cotas[$indice];
            $saldos[$participante->value] = $pagoPorParticipante[$participante->value]
                ->subtrair($cotas[$indice]);
        }

        return [$saldos, $pagoPorParticipante, $cotaPorParticipante];
    }

    /**
     * Despesa individual paga por quem não é o responsável: quem pagou tem
     * o valor a receber, o responsável tem o valor a pagar.
     *
     * @param  list<Transaction>  $lancamentos
     * @param  list<Participant>  $participantes
     * @return array<string, Money> Ajuste por participante (positivo = adiantou pelo outro)
     */
    private function ajustesDeDividaCruzada(array $lancamentos, array $participantes): array
    {
        $ajustes = [];
        foreach ($participantes as $participante) {
            $ajustes[$participante->value] = Money::zero();
        }

        foreach ($lancamentos as $lancamento) {
            if (! $lancamento->isCrossDebt()) {
                continue;
            }

            $pagador = $lancamento->whoPaid()->value;
            $responsavel = $lancamento->responsible()->value; // isCrossDebt() garante não-nulo

            $ajustes[$pagador] = ($ajustes[$pagador] ?? Money::zero())->somar($lancamento->amount());
            $ajustes[$responsavel] = ($ajustes[$responsavel] ?? Money::zero())->subtrair($lancamento->amount());
        }

        return $ajustes;
    }
}
