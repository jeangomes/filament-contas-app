<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use DomainException;

final class ValorMonetarioInvalido extends DomainException
{
    public static function paraEntrada(string $entrada): self
    {
        return new self(sprintf('Valor monetário inválido: "%s".', $entrada));
    }
}
