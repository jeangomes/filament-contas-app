<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;

/**
 * Representa "quem recebe a diferença de quem": o acerto final entre dois
 * participantes depois do rateio de despesas comuns e dos ajustes de
 * dívida cruzada.
 */
final readonly class Acerto
{
    public function __construct(
        public Participant $recebe,
        public Participant $paga,
        public Money        $valor, // sempre positivo
    ) {
    }
}
