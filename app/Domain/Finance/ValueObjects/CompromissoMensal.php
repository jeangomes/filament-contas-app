<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

/**
 * Quanto de parcela já está comprometido numa competência futura — a soma das
 * parcelas que ainda vão cair naquele mês.
 */
final readonly class CompromissoMensal
{
    public function __construct(
        public ReferenceMonth $mes,
        public Money $valor,
    ) {
    }
}
