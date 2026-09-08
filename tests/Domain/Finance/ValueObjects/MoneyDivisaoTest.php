<?php

namespace Tests\Domain\Finance\ValueObjects;

use App\Domain\Finance\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyDivisaoTest extends TestCase
{
    #[Test]
    public function divide_valor_exato_em_partes_iguais(): void
    {
        $partes = Money::emCentavos(10000)->dividirIgualmente(2);

        $this->assertCount(2, $partes);
        $this->assertTrue($partes[0]->equals(Money::emCentavos(5000)));
        $this->assertTrue($partes[1]->equals(Money::emCentavos(5000)));
    }

    #[Test]
    public function distribui_o_centavo_restante_sem_perder_nem_criar_dinheiro(): void
    {
        $partes = Money::emCentavos(10001)->dividirIgualmente(2);

        $this->assertTrue($partes[0]->equals(Money::emCentavos(5001)));
        $this->assertTrue($partes[1]->equals(Money::emCentavos(5000)));
        $this->assertTrue($this->soma($partes)->equals(Money::emCentavos(10001)));
    }

    #[Test]
    public function divide_em_tres_partes_distribuindo_o_resto(): void
    {
        $partes = Money::emCentavos(100)->dividirIgualmente(3);

        $this->assertTrue($partes[0]->equals(Money::emCentavos(34)));
        $this->assertTrue($partes[1]->equals(Money::emCentavos(33)));
        $this->assertTrue($partes[2]->equals(Money::emCentavos(33)));
        $this->assertTrue($this->soma($partes)->equals(Money::emCentavos(100)));
    }

    #[Test]
    public function divide_valor_negativo_sem_perder_centavo(): void
    {
        $partes = Money::emCentavos(-10001)->dividirIgualmente(2);

        $this->assertTrue($this->soma($partes)->equals(Money::emCentavos(-10001)));
    }

    #[Test]
    public function rejeita_divisao_por_menos_de_uma_parte(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::emCentavos(100)->dividirIgualmente(0);
    }

    /**
     * @param  list<Money>  $partes
     */
    private function soma(array $partes): Money
    {
        return array_reduce($partes, fn (Money $acumulado, Money $parte) => $acumulado->somar($parte), Money::zero());
    }
}
