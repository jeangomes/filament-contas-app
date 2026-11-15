<?php

namespace Tests\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Services\ApuradorDeParcelamentos;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\Parcela;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApuradorDeParcelamentosTest extends TestCase
{
    private ApuradorDeParcelamentos $apurador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apurador = new ApuradorDeParcelamentos();
    }

    #[Test]
    public function reune_as_parcelas_de_uma_compra_numa_serie_so(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Decolar Com', 1, 12, 90810, '2026-07'),
            $this->parcela('Decolar Com', 2, 12, 90810, '2026-08'),
        ]);

        $this->assertCount(1, $parcelamentos);

        $serie = $parcelamentos[0];
        $this->assertSame('Decolar Com', $serie->descricao);
        $this->assertSame(2, $serie->parcelasPagas);
        $this->assertSame(12, $serie->totalDeParcelas);
        $this->assertSame('2/12', $serie->rotulo());
        $this->assertTrue($serie->totalPago->equals(Money::emCentavos(181620)));
        $this->assertSame('2026-07', $serie->primeiraCompetencia->chave());
        $this->assertSame('2026-08', $serie->ultimaCompetencia->chave());
    }

    #[Test]
    public function mesma_serie_de_pagadores_diferentes_nao_se_mistura(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Bodytech Haddock Lobo', 4, 12, 27700, '2026-02', Participant::D),
            $this->parcela('Bodytech Haddock Lobo', 9, 12, 27700, '2026-07', Participant::J),
        ]);

        $this->assertCount(2, $parcelamentos);
    }

    #[Test]
    public function parcela_repetida_nao_conta_duas_vezes(): void
    {
        // O mesmo CSV importado duas vezes — acontece de verdade.
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Casa e Video', 3, 3, 3099, '2026-02'),
            $this->parcela('Casa e Video', 3, 3, 3099, '2026-02'),
        ]);

        $this->assertCount(1, $parcelamentos);
        $this->assertTrue($parcelamentos[0]->totalPago->equals(Money::emCentavos(3099)));
    }

    #[Test]
    public function valor_da_parcela_e_o_da_mais_recente(): void
    {
        // A primeira parcela costuma vir alguns centavos diferente das demais.
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Garmin Proparts Ecomm', 1, 6, 41155, '2026-06'),
            $this->parcela('Garmin Proparts Ecomm', 2, 6, 41150, '2026-07'),
        ]);

        $serie = $parcelamentos[0];
        $this->assertTrue($serie->valorDaParcela->equals(Money::emCentavos(41150)));
        $this->assertTrue($serie->totalPago->equals(Money::emCentavos(82305)));
        // 4 parcelas restantes ao valor da mais recente.
        $this->assertTrue($serie->valorRestante()->equals(Money::emCentavos(164600)));
    }

    #[Test]
    public function serie_quitada_sai_de_andamento(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Casas Pedro', 2, 2, 10505, '2026-02'),
        ]);

        $serie = $parcelamentos[0];
        $this->assertFalse($serie->emAndamento());
        $this->assertSame(0, $serie->parcelasRestantes());
        $this->assertTrue($serie->valorRestante()->ehZero());
    }

    #[Test]
    public function projeta_a_competencia_final_a_partir_da_ultima_parcela(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Decolar Com', 2, 12, 90810, '2026-08'),
        ]);

        $serie = $parcelamentos[0];
        $this->assertTrue($serie->emAndamento());
        $this->assertSame(10, $serie->parcelasRestantes());
        // 08/2026 é a parcela 2; a 12 cai 10 meses depois.
        $this->assertSame('2027-06', $serie->competenciaFinal()->chave());
    }

    #[Test]
    public function lancamento_sem_parcela_e_ignorado(): void
    {
        $avulso = new Transaction(
            date: new DateTimeImmutable('2026-08-01'),
            description: 'Deezerbr Deezer',
            amount: Money::emCentavos(3990),
            whoPaid: Participant::J,
            scope: ExpenseScope::Individual,
        );

        $this->assertSame([], $this->apurador->apurar([$avulso]));
    }

    #[Test]
    public function pgto_de_fatura_e_ignorado(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Decolar Com', 2, 12, 90810, '2026-08', type: TransactionType::BillPayment),
        ]);

        $this->assertSame([], $parcelamentos);
    }

    #[Test]
    public function em_andamento_vem_antes_das_quitadas(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Casa e Video', 3, 3, 3099, '2026-02'),
            $this->parcela('Decolar Com', 2, 12, 90810, '2026-08'),
        ]);

        $this->assertSame('Decolar Com', $parcelamentos[0]->descricao);
        $this->assertSame('Casa e Video', $parcelamentos[1]->descricao);
    }

    #[Test]
    public function projeta_compromisso_mes_a_mes_somando_series(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Decolar Com', 10, 12, 90810, '2026-11'),          // faltam 12/2026 e 01/2027
            $this->parcela('Dl*Starlink Braz', 7, 8, 21000, '2026-11', Participant::D), // falta 12/2026
        ]);

        $compromissos = $this->apurador->projetar($parcelamentos);

        $this->assertCount(2, $compromissos);

        // Virada de ano: a parcela 12 da Decolar cai em 01/2027.
        $this->assertSame('2026-12', $compromissos[0]->mes->chave());
        $this->assertTrue($compromissos[0]->valor->equals(Money::emCentavos(111810)));

        $this->assertSame('2027-01', $compromissos[1]->mes->chave());
        $this->assertTrue($compromissos[1]->valor->equals(Money::emCentavos(90810)));
    }

    #[Test]
    public function projecao_ignora_competencias_ja_passadas(): void
    {
        // Série interrompida: a parcela 2/4 é de 2025-08 e nenhuma fatura
        // seguinte foi importada. As parcelas 3 e 4 projetam para 2025-09 e
        // 2025-10 — passado, não compromisso.
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Renner', 2, 4, 7092, '2025-08'),
        ]);

        $this->assertCount(2, $this->apurador->projetar($parcelamentos));
        $this->assertSame([], $this->apurador->projetar(
            $parcelamentos,
            ReferenceMonth::fromString('2026-09'),
        ));
    }

    #[Test]
    public function serie_sem_lancamento_recente_e_marcada_como_defasada(): void
    {
        $atrasada = $this->apurador->apurar([$this->parcela('Renner', 2, 4, 7092, '2025-08')])[0];
        $emDia = $this->apurador->apurar([$this->parcela('Decolar Com', 2, 12, 90810, '2026-09')])[0];

        $agora = ReferenceMonth::fromString('2026-09');

        $this->assertTrue($atrasada->defasadaEm($agora));
        $this->assertFalse($emDia->defasadaEm($agora));
    }

    #[Test]
    public function serie_quitada_nunca_esta_defasada(): void
    {
        $quitada = $this->apurador->apurar([$this->parcela('Casas Pedro', 2, 2, 10505, '2025-02')])[0];

        $this->assertFalse($quitada->defasadaEm(ReferenceMonth::fromString('2026-09')));
    }

    #[Test]
    public function serie_quitada_nao_gera_compromisso_futuro(): void
    {
        $parcelamentos = $this->apurador->apurar([
            $this->parcela('Casas Pedro', 2, 2, 10505, '2026-02'),
        ]);

        $this->assertSame([], $this->apurador->projetar($parcelamentos));
    }

    private function parcela(
        string $serie,
        int $atual,
        int $total,
        int $centavos,
        string $competencia,
        Participant $quemPagou = Participant::J,
        TransactionType $type = TransactionType::VariableExpense,
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable($competencia . '-10'),
            description: "{$serie} - Parcela {$atual}/{$total}",
            amount: Money::emCentavos($centavos),
            whoPaid: $quemPagou,
            scope: ExpenseScope::Individual,
            type: $type,
            deFatura: true,
            parcela: Parcela::de($atual, $total),
        );
    }
}
