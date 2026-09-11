<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;

/**
 * O fechamento do mês sob a ótica de um participante.
 *
 * Duas perguntas diferentes convivem aqui, e vale não confundi-las:
 * `custoDeVida` responde "quanto a vida dele custou"; `saldo` responde "ele
 * adiantou ou ficou devendo". Quem paga o quê é o acerto, não o custo.
 */
final readonly class FechamentoDoParticipante
{
    public function __construct(
        public Participant $participante,
        public Money       $cartaoTotal,
        public Money       $cartaoComum,
        public Money       $cartaoIndividual,
        public Money       $pagoComum,
        public Money       $cotaComum,
        public Money       $ajusteDividaCruzada,
        public Money       $saldo,
        public Money       $custoDeVida,
    ) {
    }
}
