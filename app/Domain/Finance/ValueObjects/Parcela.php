<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Exceptions\ParcelaInvalida;

/**
 * A posição de um lançamento dentro de uma compra parcelada: "3 de 6".
 *
 * O extrato do cartão só informa isso como sufixo da descrição
 * ("Garmin Proparts Ecomm - Parcela 3/6"), então o parsing mora aqui — do
 * mesmo jeito que {@see ReferenceMonth::fromString()} conhece o formato
 * "YYYY-MM".
 */
final readonly class Parcela
{
    /**
     * Âncora no **fim** da string, e não no primeiro " - ": há descrição com
     * hífen no meio ("EBW*NuViagens - NuPay - Parcela 2/3") que seria cortada
     * no lugar errado.
     */
    private const string PADRAO = '/\s+-\s+Parcela\s+(\d+)\/(\d+)\s*$/iu';

    private function __construct(
        private int $atual,
        private int $total,
    ) {
    }

    public static function de(int $atual, int $total): self
    {
        if ($total < 1 || $atual < 1 || $atual > $total) {
            throw ParcelaInvalida::paraEntrada($atual, $total);
        }

        return new self($atual, $total);
    }

    /**
     * Devolve `null` quando a descrição não é de uma compra parcelada — que é
     * o caso da maioria das linhas do extrato, não um erro.
     */
    public static function extrairDe(string $descricao): ?self
    {
        if (! preg_match(self::PADRAO, $descricao, $partes)) {
            return null;
        }

        $atual = (int) $partes[1];
        $total = (int) $partes[2];

        // Um extrato com "Parcela 0/6" ou "7/6" é lixo de origem, não motivo
        // para derrubar a importação inteira.
        if ($total < 1 || $atual < 1 || $atual > $total) {
            return null;
        }

        return new self($atual, $total);
    }

    /**
     * O nome da série, sem o sufixo da parcela — é o que permite reconhecer
     * "Garmin Proparts Ecomm - Parcela 3/6" e "- Parcela 4/6" como a mesma
     * compra. A descrição gravada continua íntegra; a remoção é de leitura.
     */
    public static function descricaoDaSerie(string $descricao): string
    {
        return preg_replace(self::PADRAO, '', $descricao) ?? $descricao;
    }

    public function atual(): int
    {
        return $this->atual;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function restantes(): int
    {
        return $this->total - $this->atual;
    }

    public function ehUltima(): bool
    {
        return $this->atual === $this->total;
    }

    public function rotulo(): string
    {
        return sprintf('%d/%d', $this->atual, $this->total);
    }

    public function equals(self $outro): bool
    {
        return $this->atual === $outro->atual && $this->total === $outro->total;
    }
}
