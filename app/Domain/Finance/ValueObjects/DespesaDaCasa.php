<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

/**
 * Uma linha do detalhe das despesas da casa no mês: a descrição e quanto
 * somou. Corresponde a uma linha de `monthly_home_expenses`.
 */
final readonly class DespesaDaCasa
{
    public function __construct(
        public string $label,
        public Money  $valor,
    ) {
    }
}
