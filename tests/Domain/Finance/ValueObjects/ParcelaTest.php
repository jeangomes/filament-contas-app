<?php

namespace Tests\Domain\Finance\ValueObjects;

use App\Domain\Finance\Exceptions\ParcelaInvalida;
use App\Domain\Finance\ValueObjects\Parcela;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParcelaTest extends TestCase
{
    #[Test]
    public function extrai_parcela_do_sufixo_da_descricao(): void
    {
        $parcela = Parcela::extrairDe('Garmin Proparts Ecomm - Parcela 3/6');

        $this->assertNotNull($parcela);
        $this->assertSame(3, $parcela->atual());
        $this->assertSame(6, $parcela->total());
        $this->assertSame('3/6', $parcela->rotulo());
    }

    #[Test]
    public function ancora_no_fim_e_ignora_hifen_no_meio_da_descricao(): void
    {
        // Se o padrão cortasse no primeiro " - ", a série viraria "EBW*NuViagens".
        $descricao = 'EBW*NuViagens - NuPay - Parcela 2/3';

        $parcela = Parcela::extrairDe($descricao);

        $this->assertNotNull($parcela);
        $this->assertSame(2, $parcela->atual());
        $this->assertSame(3, $parcela->total());
        $this->assertSame('EBW*NuViagens - NuPay', Parcela::descricaoDaSerie($descricao));
    }

    #[Test]
    public function descricao_sem_parcela_nao_casa(): void
    {
        $this->assertNull(Parcela::extrairDe('Deezerbr Deezer'));
        $this->assertSame('Deezerbr Deezer', Parcela::descricaoDaSerie('Deezerbr Deezer'));
    }

    #[Test]
    public function parcela_fora_do_intervalo_no_extrato_e_ignorada(): void
    {
        $this->assertNull(Parcela::extrairDe('Loja Qualquer - Parcela 0/6'));
        $this->assertNull(Parcela::extrairDe('Loja Qualquer - Parcela 7/6'));
    }

    #[Test]
    public function construcao_direta_com_parcela_fora_do_intervalo_lanca(): void
    {
        $this->expectException(ParcelaInvalida::class);

        Parcela::de(7, 6);
    }

    #[Test]
    public function restantes_e_eh_ultima(): void
    {
        $terceiraDeSeis = Parcela::de(3, 6);
        $ultima = Parcela::de(12, 12);

        $this->assertSame(3, $terceiraDeSeis->restantes());
        $this->assertFalse($terceiraDeSeis->ehUltima());

        $this->assertSame(0, $ultima->restantes());
        $this->assertTrue($ultima->ehUltima());
    }

    #[Test]
    public function duas_parcelas_com_os_mesmos_numeros_sao_iguais(): void
    {
        $this->assertTrue(Parcela::de(3, 6)->equals(Parcela::de(3, 6)));
        $this->assertFalse(Parcela::de(3, 6)->equals(Parcela::de(4, 6)));
    }
}
