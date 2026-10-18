<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use DomainException;

final class ParcelaInvalida extends DomainException
{
    public static function paraEntrada(int $atual, int $total): self
    {
        return new self(sprintf('Parcela inválida: "%d/%d". Espera-se 1 <= atual <= total.', $atual, $total));
    }
}
