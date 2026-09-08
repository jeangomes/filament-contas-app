<?php

namespace Tests\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\MonthlyBalance;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MonthlyBalanceTest extends TestCase
{
    #[Test]
    public function quem_esta_positivo_recebe_a_diferenca_de_quem_esta_negativo(): void
    {
        $saldo = new MonthlyBalance(ReferenceMonth::fromString('2026-01'), [
            'D' => Money::emCentavos(500),
            'J' => Money::emCentavos(-500),
        ]);

        $acertos = $saldo->acertos();

        $this->assertCount(1, $acertos);
        $this->assertSame(Participant::D, $acertos[0]->recebe);
        $this->assertSame(Participant::J, $acertos[0]->paga);
        $this->assertTrue($acertos[0]->valor->equals(Money::emCentavos(500)));
    }

    #[Test]
    public function inverte_a_direcao_do_acerto_conforme_quem_esta_negativo(): void
    {
        $saldo = new MonthlyBalance(ReferenceMonth::fromString('2026-01'), [
            'D' => Money::emCentavos(-500),
            'J' => Money::emCentavos(500),
        ]);

        $acertos = $saldo->acertos();

        $this->assertCount(1, $acertos);
        $this->assertSame(Participant::J, $acertos[0]->recebe);
        $this->assertSame(Participant::D, $acertos[0]->paga);
        $this->assertTrue($acertos[0]->valor->equals(Money::emCentavos(500)));
    }

    #[Test]
    public function saldos_zerados_nao_geram_acerto(): void
    {
        $saldo = new MonthlyBalance(ReferenceMonth::fromString('2026-01'), [
            'D' => Money::zero(),
            'J' => Money::zero(),
        ]);

        $this->assertSame([], $saldo->acertos());
    }

    #[Test]
    public function o_valor_do_acerto_nunca_e_negativo(): void
    {
        $saldo = new MonthlyBalance(ReferenceMonth::fromString('2026-01'), [
            'D' => Money::emCentavos(-500),
            'J' => Money::emCentavos(500),
        ]);

        $this->assertTrue($saldo->acertos()[0]->valor->ehPositivo());
    }

    #[Test]
    public function saldo_de_participante_sem_entrada_e_zero(): void
    {
        $saldo = new MonthlyBalance(ReferenceMonth::fromString('2026-01'), [
            'D' => Money::emCentavos(500),
        ]);

        $this->assertTrue($saldo->saldoDe(Participant::J)->ehZero());
    }
}
