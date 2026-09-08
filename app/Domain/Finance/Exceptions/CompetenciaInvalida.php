<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use DomainException;

final class CompetenciaInvalida extends DomainException
{
    public static function paraEntrada(string $entrada): self
    {
        return new self(sprintf('Mês de competência inválido: "%s". Use YYYY-MM.', $entrada));
    }
}
