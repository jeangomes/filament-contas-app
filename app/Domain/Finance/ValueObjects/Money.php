<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

/**
 * Money monetário representado em centavos (inteiro), para evitar a
 * imprecisão de ponto flutuante em operações financeiras.
 */
final readonly class Money
{
    private function __construct(
        private int $centavos,
    )
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function emCentavos(int $centavos): self
    {
        return new self($centavos);
    }

    public function centavos(): int
    {
        return $this->centavos;
    }

    public function ehPositivo(): bool
    {
        return $this->centavos > 0;
    }

    public function ehNegativo(): bool
    {
        return $this->centavos < 0;
    }

    public function ehZero(): bool
    {
        return $this->centavos === 0;
    }

    public function somar(self $outro): self
    {
        return new self(
            $this->centavos + $outro->centavos,
        );
    }

    public function subtrair(self $outro): self
    {
        return new self(
            $this->centavos - $outro->centavos,
        );
    }

    public function negar(): self
    {
        return new self(
            -$this->centavos,
        );
    }

    public function absoluto(): self
    {
        return new self(
            abs($this->centavos),
        );
    }

    public function equals(self $outro): bool
    {
        return $this->centavos === $outro->centavos;
    }

    /**
     * Representação decimal com ponto, pronta para colunas DECIMAL do MySQL.
     * Ex.: -282551 centavos -> "-2825.51".
     */
    public function paraDecimal(): string
    {
        $absoluto = abs($this->centavos);

        return sprintf(
            '%s%d.%02d',
            $this->centavos < 0 ? '-' : '',
            intdiv($absoluto, 100),
            $absoluto % 100,
        );
    }

    public function ehCredito(): bool
    {
        return $this->centavos < 0;
    }

    public function ehDespesa(): bool
    {
        return $this->centavos > 0;
    }

}
