<?php

declare(strict_types=1);

namespace App\Services\Relatorio;

use App\Domain\Finance\ValueObjects\Money;

/**
 * Uma igualdade que o relatório exibe para provar que os números fecham:
 * os dois lados chegam por caminhos diferentes e têm que dar o mesmo valor.
 */
final readonly class Conferencia
{
    public function __construct(
        public string $regra,     // "casa + comum dos cartões = total comum"
        public Money  $esquerda,
        public Money  $direita,
    ) {
    }

    public function fecha(): bool
    {
        return $this->esquerda->equals($this->direita);
    }
}
