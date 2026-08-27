<?php

namespace App\Infra\Csv;

use App\Domain\Finance\Exceptions\ValorMonetarioInvalido;
use App\Domain\Finance\ValueObjects\Money;

/**
 * Converte valores no formato brasileiro (ponto de milhar, vírgula decimal,
 * negativos com prefixo "-") em um Valor de domínio, em centavos.
 *
 * A conversão é feita por manipulação de strings (sem float) para não
 * introduzir imprecisão. O separador decimal é detectado como o separador
 * mais à direita, de modo que o formato antigo (US: "38.00") também é aceito.
 *
 * Pressuposto: valores com casas decimais sempre trazem o separador decimal
 * explícito — verdadeiro para a fatura atual, em que todo valor vem com ",xx".
 */
final class ConversorDeValorBrasileiro
{
    public function converter(string $bruto): Money
    {
        $original = $bruto;
        $bruto = trim($bruto);

        if ($bruto === '') {
            throw ValorMonetarioInvalido::paraEntrada($original);
        }

        $negativo = str_starts_with($bruto, '-');

        // mantém apenas dígitos e separadores
        $limpo = preg_replace('/[^\d.,]/', '', $bruto) ?? '';

        if (! preg_match('/\d/', $limpo)) {
            throw ValorMonetarioInvalido::paraEntrada($original);
        }

        $posVirgula = strrpos($limpo, ',');
        $posPonto = strrpos($limpo, '.');
        $posDecimal = max(
            $posVirgula === false ? -1 : $posVirgula,
            $posPonto === false ? -1 : $posPonto,
        );

        if ($posDecimal === -1) {
            $inteiro = $limpo;
            $decimal = '00';
        } else {
            $inteiro = preg_replace('/\D/', '', substr($limpo, 0, $posDecimal)) ?? '';
            $decimal = preg_replace('/\D/', '', substr($limpo, $posDecimal + 1)) ?? '';
        }

        $inteiro = $inteiro === '' ? '0' : $inteiro;
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        $centavos = (int) ($inteiro . $decimal);

        return Money::emCentavos($negativo ? -$centavos : $centavos);
    }
}
