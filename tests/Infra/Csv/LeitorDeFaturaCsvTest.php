<?php

namespace Tests\Infra\Csv;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Infra\Csv\LeitorDeFaturaCsv;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LeitorDeFaturaCsvTest extends TestCase
{
    private string $arquivo;

    protected function setUp(): void
    {
        $this->arquivo = tempnam(sys_get_temp_dir(), 'fatura_') . '.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->arquivo);
    }

    #[Test]
    public function le_fatura_com_bom_aspas_e_linha_em_branco(): void
    {
        $this->escrever(
            "\u{FEFF}date,title,amount\n"
            . "2026-06-12,99Food - NuPay,\"202,69\"\n"
            . "2026-06-12,\"IOF de \"\"Paddle.Net* Runcloudio\"\"\",\"1,67\"\n"
            . "2026-06-09,Pagamento recebido,\"- 2.825,51\"\n"
            . "\n" // linha em branco final não deve quebrar
        );

        $lancamentos = $this->leitor(Participant::J, ExpenseScope::Comum)->obter();

        $this->assertCount(3, $lancamentos);
        $this->assertContainsOnlyInstancesOf(Transaction::class, $lancamentos);
    }

    #[Test]
    public function normaliza_valor_e_remove_bom_do_cabecalho(): void
    {
        // Se o BOM não fosse removido, a chave 'date' não existiria e a
        // construção do DateTimeImmutable falharia — este teste cobre isso.
        $this->escrever(
            "\u{FEFF}date,title,amount\n"
            . "2026-06-08,Sorveteria Alex,\"43,00\"\n"
        );

        [$lancamento] = $this->leitor(Participant::D, ExpenseScope::Individual)->obter();

        $this->assertSame('2026-06-08', $lancamento->date()->format('Y-m-d'));
        $this->assertSame('Sorveteria Alex', $lancamento->description());
        $this->assertSame(4300, $lancamento->amount()->centavos());
        $this->assertSame(Participant::D, $lancamento->whoPaid());
        $this->assertTrue($lancamento->ehDespesaIndividual());
    }

    #[Test]
    public function preserva_aspas_internas_escapadas_na_descricao(): void
    {
        $this->escrever(
            "date,title,amount\n"
            . "2026-06-12,\"IOF de \"\"Paddle.Net* Runcloudio\"\"\",\"1,67\"\n"
        );

        [$lancamento] = $this->leitor(Participant::J, ExpenseScope::Comum)->obter();

        $this->assertSame('IOF de "Paddle.Net* Runcloudio"', $lancamento->description());
    }

    #[Test]
    public function lancamento_importado_de_fatura_nasce_marcado_como_de_fatura(): void
    {
        $this->escrever(
            "date,title,amount\n"
            . "2026-06-08,Sorveteria Alex,\"43,00\"\n"
        );

        [$lancamento] = $this->leitor(Participant::J, ExpenseScope::Comum)->obter();

        $this->assertTrue($lancamento->ehDeFatura());
    }

    #[Test]
    public function identifica_pagamento_recebido_como_credito(): void
    {
        $this->escrever(
            "date,title,amount\n"
            . "2026-06-09,Pagamento recebido,\"- 2.825,51\"\n"
        );

        [$lancamento] = $this->leitor(Participant::J, ExpenseScope::Comum)->obter();

        $this->assertTrue($lancamento->ehCredito());
        $this->assertSame(-282551, $lancamento->amount()->centavos());
    }

    private function escrever(string $conteudo): void
    {
        file_put_contents($this->arquivo, $conteudo);
    }

    private function leitor(Participant $quemPagou, ExpenseScope $tipo): LeitorDeFaturaCsv
    {
        return new LeitorDeFaturaCsv($this->arquivo, $quemPagou, $tipo);
    }
}
