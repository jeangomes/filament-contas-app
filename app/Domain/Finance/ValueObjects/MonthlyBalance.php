<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;
use LogicException;

/**
 * Saldo de um mês de competência por participante. Saldo positivo = pagou
 * mais do que devia (deve receber); saldo negativo = pagou menos do que
 * devia (deve pagar).
 */
final readonly class MonthlyBalance
{
    /**
     * @param  array<string, Money>  $saldosPorParticipante  Chave = Participant::value
     * @param  array<string, Money>  $totalPagoComum  Detalhe do rateio, preenchido pela calculadora
     * @param  array<string, Money>  $cotaComum  Detalhe do rateio, preenchido pela calculadora
     * @param  array<string, Money>  $ajusteDividaCruzada  Detalhe do rateio, preenchido pela calculadora
     */
    public function __construct(
        private ReferenceMonth $mes,
        private array          $saldosPorParticipante,
        private array          $totalPagoComum = [],
        private array          $cotaComum = [],
        private array          $ajusteDividaCruzada = [],
    ) {
    }

    public function mes(): ReferenceMonth
    {
        return $this->mes;
    }

    public function saldoDe(Participant $participante): Money
    {
        return $this->saldosPorParticipante[$participante->value] ?? Money::zero();
    }

    /**
     * Quanto o participante pagou de despesas comuns no mês (antes do rateio).
     */
    public function totalPagoComumDe(Participant $participante): Money
    {
        return $this->totalPagoComum[$participante->value] ?? Money::zero();
    }

    /**
     * Quanto o participante deveria ter pago — sua cota do total comum.
     */
    public function cotaComumDe(Participant $participante): Money
    {
        return $this->cotaComum[$participante->value] ?? Money::zero();
    }

    /**
     * Efeito das dívidas cruzadas no saldo do participante: positivo para
     * quem adiantou despesa individual do outro, negativo para quem foi
     * adiantado.
     */
    public function ajusteDeDividaCruzadaDe(Participant $participante): Money
    {
        return $this->ajusteDividaCruzada[$participante->value] ?? Money::zero();
    }

    /**
     * Acertos necessários para zerar os saldos do mês. Suporta apenas
     * exatamente 2 participantes — o único caso real do sistema hoje.
     *
     * @return list<Acerto>
     */
    public function acertos(): array
    {
        if (count($this->saldosPorParticipante) !== 2) {
            throw new LogicException('acertos() suporta apenas exatamente 2 participantes.');
        }

        $chaves = array_keys($this->saldosPorParticipante);
        $participanteA = Participant::from($chaves[0]);
        $participanteB = Participant::from($chaves[1]);
        $saldoA = $this->saldosPorParticipante[$chaves[0]];

        if ($saldoA->ehZero()) {
            return [];
        }

        return $saldoA->ehPositivo()
            ? [new Acerto(recebe: $participanteA, paga: $participanteB, valor: $saldoA)]
            : [new Acerto(recebe: $participanteB, paga: $participanteA, valor: $saldoA->negar())];
    }
}
