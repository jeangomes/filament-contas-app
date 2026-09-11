<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

/**
 * Quanto a vida de cada participante custou no mês.
 *
 * custo de vida (P) = cota de P no total das despesas comuns
 *                   + despesas individuais de responsabilidade de P
 *
 * Repare que quem *pagou* não entra na conta: adiantar dinheiro do outro não
 * encarece a própria vida. Quem pagou o quê é assunto do acerto
 * ({@see CalculadoraDeSaldoMensal}), não daqui.
 *
 * Serviço puro: não conhece banco, Eloquent nem SQL.
 */
final class CalculadoraDeCustoDeVida
{
    /**
     * @param  list<Transaction>  $lancamentos  Lançamentos de qualquer período — o filtro por competência é feito aqui.
     * @param  list<Participant>  $participantes  Quem divide as despesas comuns.
     * @return array<string, Money>  Chave = Participant::value
     */
    public function calcular(ReferenceMonth $mes, array $lancamentos, array $participantes): array
    {
        $participantes = array_values($participantes);

        $relevantes = array_filter(
            $lancamentos,
            static fn (Transaction $lancamento): bool => $lancamento->contaParaBalanco()
                && $lancamento->referenceMonth()->equals($mes),
        );

        $individuaisPorResponsavel = [];
        foreach ($participantes as $participante) {
            $individuaisPorResponsavel[$participante->value] = Money::zero();
        }

        $totalComum = Money::zero();

        foreach ($relevantes as $lancamento) {
            if ($lancamento->ehDespesaComum()) {
                $totalComum = $totalComum->somar($lancamento->amount());

                continue;
            }

            // Individual: pesa para quem é responsável, não para quem pagou.
            $responsavel = $lancamento->responsavelEfetivo()->value;
            $individuaisPorResponsavel[$responsavel] = ($individuaisPorResponsavel[$responsavel] ?? Money::zero())
                ->somar($lancamento->amount());
        }

        $cotas = $totalComum->dividirIgualmente(count($participantes));

        $custoDeVida = [];
        foreach ($participantes as $indice => $participante) {
            $custoDeVida[$participante->value] = $cotas[$indice]
                ->somar($individuaisPorResponsavel[$participante->value]);
        }

        return $custoDeVida;
    }
}
