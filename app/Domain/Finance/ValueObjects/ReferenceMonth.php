<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Exceptions\CompetenciaInvalida;
use DateTimeImmutable;

/**
 * Mês de competência (ex.: item de fatura de cartão competência = mês do
 * vencimento; transação avulsa competência = mês da própria data).
 */
final readonly class ReferenceMonth
{
    private function __construct(
        private int $ano,
        private int $mes,
    ) {
    }

    public static function fromDate(DateTimeImmutable $data): self
    {
        return new self((int) $data->format('Y'), (int) $data->format('n'));
    }

    public static function fromString(string $chave): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $chave, $partes)) {
            throw CompetenciaInvalida::paraEntrada($chave);
        }

        $mes = (int) $partes[2];

        if ($mes < 1 || $mes > 12) {
            throw CompetenciaInvalida::paraEntrada($chave);
        }

        return new self((int) $partes[1], $mes);
    }

    public function proximoMes(): self
    {
        return $this->mes === 12
            ? new self($this->ano + 1, 1)
            : new self($this->ano, $this->mes + 1);
    }

    public function ano(): int
    {
        return $this->ano;
    }

    public function numeroDoMes(): int
    {
        return $this->mes;
    }

    public function equals(self $outro): bool
    {
        return $this->ano === $outro->ano && $this->mes === $outro->mes;
    }

    public function chave(): string
    {
        return sprintf('%04d-%02d', $this->ano, $this->mes);
    }
}
