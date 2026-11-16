<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

/**
 * Formatação de dinheiro e competência para as páginas que exibem valores
 * vindos direto do domínio (sem model, logo sem `->money('BRL')`).
 */
trait FormataValores
{
    /**
     * Formata a partir da representação decimal exata do `Money` — os
     * centavos nunca passam por float no caminho até a tela.
     */
    private function formatMoney(Money $valor): string
    {
        [$inteiro, $centavos] = explode('.', ltrim($valor->paraDecimal(), '-'));
        $sinal = $valor->ehNegativo() ? '-' : '';

        return $sinal . 'R$ ' . number_format((int) $inteiro, 0, ',', '.') . ',' . $centavos;
    }

    private function formatCompetencia(ReferenceMonth $mes): string
    {
        return sprintf('%02d/%04d', $mes->numeroDoMes(), $mes->ano());
    }
}
