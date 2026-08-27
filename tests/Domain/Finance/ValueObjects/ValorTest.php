<?php

namespace Tests\Domain\Finance\ValueObjects;

use App\Domain\Finance\ValueObjects\Money;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ValorTest extends TestCase
{
    #[Test]
    public function identifica_credito_despesa_e_zero(): void
    {
        $this->assertTrue(Money::emCentavos(-100)->ehCredito());
        $this->assertTrue(Money::emCentavos(100)->ehDespesa());
        $this->assertTrue(Money::emCentavos(0)->ehZero());
    }

    #[Test]
    public function formata_para_decimal_do_mysql(): void
    {
        $this->assertSame('2825.51', Money::emCentavos(282551)->paraDecimal());
        $this->assertSame('-2825.51', Money::emCentavos(-282551)->paraDecimal());
        $this->assertSame('0.05', Money::emCentavos(5)->paraDecimal());
        $this->assertSame('9.50', Money::emCentavos(950)->paraDecimal());
    }

    #[Test]
    public function compara_valores_por_igualdade(): void
    {
        $this->assertTrue(Money::emCentavos(100)->equals(Money::emCentavos(100)));
        $this->assertFalse(Money::emCentavos(100)->equals(Money::emCentavos(101)));
    }
}
