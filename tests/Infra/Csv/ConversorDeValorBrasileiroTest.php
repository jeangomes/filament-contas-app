<?php

namespace Tests\Infra\Csv;

use App\Domain\Finance\Exceptions\ValorMonetarioInvalido;
use App\Infra\Csv\ConversorDeValorBrasileiro;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConversorDeValorBrasileiroTest extends TestCase
{
    private ConversorDeValorBrasileiro $conversor;

    protected function setUp(): void
    {
        $this->conversor = new ConversorDeValorBrasileiro();
    }

    #[Test]
    #[DataProvider('valoresValidos')]
    public function converte_para_centavos(string $entrada, int $centavosEsperados): void
    {
        $this->assertSame(
            $centavosEsperados,
            $this->conversor->converter($entrada)->centavos(),
        );
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function valoresValidos(): iterable
    {
        yield 'formato BR simples' => ['202,69', 20269];
        yield 'centavos com zero à esquerda' => ['30,05', 3005];
        yield 'valor pequeno' => ['1,67', 167];
        yield 'negativo com milhar e espaço' => ['- 2.825,51', -282551];
        yield 'negativo pequeno' => ['- 1,67', -167];
        yield 'milhar com decimais' => ['1.234,00', 123400];
        yield 'formato US antigo' => ['38.00', 3800];
        yield 'inteiro sem separador' => ['150', 15000];
        yield 'com prefixo de moeda' => ['R$ 1.000,50', 100050];
        yield 'uma casa decimal' => ['9,5', 950];
    }

    #[Test]
    public function credito_e_representado_como_negativo(): void
    {
        $valor = $this->conversor->converter('- 2.825,51');

        $this->assertTrue($valor->ehCredito());
        $this->assertSame('-2825.51', $valor->paraDecimal());
    }

    #[Test]
    #[DataProvider('valoresInvalidos')]
    public function rejeita_entradas_sem_digitos(string $entrada): void
    {
        $this->expectException(ValorMonetarioInvalido::class);

        $this->conversor->converter($entrada);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function valoresInvalidos(): iterable
    {
        yield 'vazio' => [''];
        yield 'só espaços' => ['   '];
        yield 'sem dígitos' => ['abc'];
        yield 'só separador' => [','];
    }
}
