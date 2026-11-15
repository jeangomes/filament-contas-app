<?php

namespace Tests\Domain\Finance\ValueObjects;

use App\Domain\Finance\Exceptions\CompetenciaInvalida;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ReferenceMonthTest extends TestCase
{
    #[Test]
    public function extrai_ano_e_mes_de_uma_chave_valida(): void
    {
        $mes = ReferenceMonth::fromString('2026-01');

        $this->assertSame(2026, $mes->ano());
        $this->assertSame(1, $mes->numeroDoMes());
    }

    #[Test]
    #[TestWith(['2026-13'])]
    #[TestWith(['abc'])]
    #[TestWith(['2026-1'])]
    #[TestWith(['2026-00'])]
    #[TestWith(['26-01'])]
    public function rejeita_chaves_invalidas(string $chaveInvalida): void
    {
        $this->expectException(CompetenciaInvalida::class);

        ReferenceMonth::fromString($chaveInvalida);
    }

    #[Test]
    public function avanca_para_o_proximo_mes_inclusive_virada_de_ano(): void
    {
        $this->assertTrue(
            ReferenceMonth::fromString('2026-12')->proximoMes()->equals(ReferenceMonth::fromString('2027-01')),
        );

        $this->assertTrue(
            ReferenceMonth::fromString('2026-01')->proximoMes()->equals(ReferenceMonth::fromString('2026-02')),
        );
    }

    #[Test]
    public function volta_para_o_mes_anterior_inclusive_virada_de_ano(): void
    {
        $this->assertTrue(
            ReferenceMonth::fromString('2027-01')->mesAnterior()->equals(ReferenceMonth::fromString('2026-12')),
        );

        $this->assertTrue(
            ReferenceMonth::fromString('2026-03')->mesAnterior()->equals(ReferenceMonth::fromString('2026-02')),
        );
    }

    #[Test]
    public function resolve_a_partir_de_uma_data(): void
    {
        $mes = ReferenceMonth::fromDate(new DateTimeImmutable('2026-03-15'));

        $this->assertSame('2026-03', $mes->chave());
    }

    #[Test]
    public function compara_por_igualdade_de_ano_e_mes(): void
    {
        $this->assertTrue(ReferenceMonth::fromString('2026-03')->equals(ReferenceMonth::fromString('2026-03')));
        $this->assertFalse(ReferenceMonth::fromString('2026-03')->equals(ReferenceMonth::fromString('2026-04')));
        $this->assertFalse(ReferenceMonth::fromString('2026-03')->equals(ReferenceMonth::fromString('2027-03')));
    }
}
