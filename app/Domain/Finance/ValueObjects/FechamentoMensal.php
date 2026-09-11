<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;
use LogicException;

/**
 * Tudo o que o fechamento de um mês de competência produz: os totais do mês,
 * o detalhe da casa, o resultado de cada participante e o acerto entre eles.
 *
 * O formato espelha de propósito o que é persistido — a camada de aplicação
 * só traduz, não recompõe nem recalcula nada.
 *
 * Invariante: `totalCasa` + soma dos `cartaoComum` == `totalComum`. Toda
 * despesa comum ou veio de uma fatura, ou é da casa.
 */
final readonly class FechamentoMensal
{
    /**
     * @param  list<DespesaDaCasa>  $despesasDaCasa
     * @param  array<string, FechamentoDoParticipante>  $participantes  Chave = Participant::value
     */
    public function __construct(
        private ReferenceMonth $mes,
        private Money          $totalComum,
        private Money          $totalCasa,
        private array          $despesasDaCasa,
        private array          $participantes,
        private MonthlyBalance $saldoMensal,
    ) {
    }

    public function mes(): ReferenceMonth
    {
        return $this->mes;
    }

    public function totalComum(): Money
    {
        return $this->totalComum;
    }

    public function totalCasa(): Money
    {
        return $this->totalCasa;
    }

    /**
     * @return list<DespesaDaCasa>
     */
    public function despesasDaCasa(): array
    {
        return $this->despesasDaCasa;
    }

    public function de(Participant $participante): FechamentoDoParticipante
    {
        return $this->participantes[$participante->value]
            ?? throw new LogicException(
                sprintf('Participante "%s" não faz parte deste fechamento.', $participante->value),
            );
    }

    /**
     * @return list<FechamentoDoParticipante>
     */
    public function participantes(): array
    {
        return array_values($this->participantes);
    }

    /**
     * Quem paga a diferença para quem.
     *
     * @return list<Acerto>
     */
    public function acertos(): array
    {
        return $this->saldoMensal->acertos();
    }
}
