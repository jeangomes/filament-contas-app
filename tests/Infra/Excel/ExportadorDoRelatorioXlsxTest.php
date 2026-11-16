<?php

namespace Tests\Infra\Excel;

use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Infra\Excel\ExportadorDoRelatorioXlsx;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\RelatorioDaCompetenciaService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Grava de verdade e relê com o Reader do openspout: é o que garante que o
 * arquivo abre e que os números chegam como número, não como texto.
 */
final class ExportadorDoRelatorioXlsxTest extends TestCase
{
    use RefreshDatabase;

    private string $caminho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caminho = tempnam(sys_get_temp_dir(), 'teste-relatorio-') . '.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->caminho);
        @unlink(substr($this->caminho, 0, -5));

        parent::tearDown();
    }

    #[Test]
    public function grava_as_quatro_abas_com_todos_os_lancamentos(): void
    {
        $this->mesMisto();

        $abas = $this->exportar('2026-07');

        $this->assertSame(['Resumo', 'Lançamentos', 'Dívidas cruzadas', 'Casa'], array_keys($abas));

        // Cabeçalho + 4 lançamentos.
        $this->assertCount(5, $abas['Lançamentos']);
        $this->assertSame(
            ['Data da compra', 'Descrição', 'Parcela', 'Origem', 'Categoria', 'Pagou', 'Responsável', 'Rubrica', 'Entra no rateio', 'Valor'],
            $abas['Lançamentos'][0],
        );
    }

    #[Test]
    public function resumo_traz_os_numeros_do_dominio_como_numero(): void
    {
        $this->mesMisto();

        $resumo = $this->exportar('2026-07')['Resumo'];

        // Comum: 300 (casa, J) + 100 (cartão D) = 400 → cota de 200.
        $this->assertEquals(400.00, $this->valorDe($resumo, 'Total comum (o que é rateado)'));
        $this->assertEquals(450.00, $this->valorDe($resumo, 'Total gasto (comuns + individuais)'));
        $this->assertEquals(-500.00, $this->valorDe($resumo, 'Fora do rateio (pagamento de fatura)'));

        $this->assertSame('07/2026', $this->valorDe($resumo, 'Competência (mês do vencimento)'));
        $this->assertSame('06/2026', $this->valorDe($resumo, 'Mês dos gastos'));
        $this->assertSame('Ainda não fechado', $this->valorDe($resumo, 'Fechamento salvo'));

        // D: pagou 100 − cota 200 − 50 adiantados por J = −150. J recebe.
        $saldos = $this->linhaDe($resumo, 'Saldo (diferença + ajuste)');
        $this->assertEquals(-150.00, $saldos[1]);
        $this->assertEquals(150.00, $saldos[2]);
        $this->assertSame('D', $this->valorDe($resumo, 'Quem paga'));
        $this->assertSame('J', $this->valorDe($resumo, 'Quem recebe'));
        $this->assertEquals(150.00, $this->valorDe($resumo, 'Valor do acerto'));

        // Custo de vida de D: cota 200 + 50 de responsabilidade dele.
        $this->assertEquals(250.00, $this->linhaDe($resumo, 'Custo de vida', ocorrencia: 2)[1]);
    }

    #[Test]
    public function todas_as_conferencias_saem_como_ok(): void
    {
        $this->mesMisto();

        $resumo = $this->exportar('2026-07')['Resumo'];

        foreach ([
            'soma dos saldos = zero',
            'soma dos custos de vida = total gasto',
            'casa + comum dos cartões = total comum',
            'cartões + casa + individuais avulsas = total gasto',
        ] as $regra) {
            $this->assertSame('OK', $this->linhaDe($resumo, $regra)[3], $regra);
        }
    }

    #[Test]
    public function pagamento_de_fatura_esta_nos_lancamentos_fora_do_rateio(): void
    {
        $this->mesMisto();

        $lancamentos = $this->exportar('2026-07')['Lançamentos'];
        $pagamento = $this->linhaDe($lancamentos, 'Pagamento recebido', coluna: 1);

        $this->assertSame('Fora do rateio', $pagamento[7]);
        $this->assertSame('Não', $pagamento[8]);
        $this->assertEquals(-500.00, $pagamento[9]);

        $mercado = $this->linhaDe($lancamentos, 'Mercado', coluna: 1);

        // Data da compra como data de verdade, não texto.
        $this->assertInstanceOf(DateTimeInterface::class, $mercado[0]);
        $this->assertSame('2026-06-12', $mercado[0]->format('Y-m-d'));
        $this->assertSame('Cartão D · venc. 11/07/2026', $mercado[3]);
        $this->assertSame('Sim', $mercado[8]);
        $this->assertEquals(100.00, $mercado[9]);
    }

    #[Test]
    public function abas_de_dividas_cruzadas_e_casa_trazem_o_detalhe(): void
    {
        $this->mesMisto();

        $abas = $this->exportar('2026-07');

        $cruzada = $this->linhaDe($abas['Dívidas cruzadas'], 'Remedio do D', coluna: 1);
        $this->assertSame('J', $cruzada[2]);
        $this->assertSame('D', $cruzada[3]);
        $this->assertEquals(50.00, $cruzada[4]);

        $this->assertEquals(300.00, $this->valorDe($abas['Casa'], 'Aluguel'));
        $this->assertEquals(300.00, $this->valorDe($abas['Casa'], 'Total'));
    }

    #[Test]
    public function aba_de_lancamentos_tem_cabecalho_congelado_e_autofiltro(): void
    {
        $this->mesMisto();
        $this->exportar('2026-07');

        // O Reader não expõe isso; olha o XML da segunda aba direto.
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->caminho));
        $xml = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();

        $this->assertStringContainsString('state="frozen"', $xml);
        $this->assertStringContainsString('<autoFilter ref="A1:J5"', $xml);
    }

    #[Test]
    public function competencia_vazia_gera_arquivo_valido(): void
    {
        $abas = $this->exportar('2026-07');

        $this->assertCount(4, $abas);
        $this->assertCount(1, $abas['Lançamentos']); // só o cabeçalho
        $this->assertEquals(0.00, $this->valorDe($abas['Resumo'], 'Total comum (o que é rateado)'));
        $this->assertSame('—', $this->valorDe($abas['Resumo'], 'Quem paga'));
        $this->assertEquals(0.00, $this->valorDe($abas['Casa'], 'Total'));
    }

    /**
     * @return array<string, list<list<mixed>>>  Linhas de cada aba, pelo nome
     */
    private function exportar(string $competencia): array
    {
        $relatorio = app(RelatorioDaCompetenciaService::class)->gerar(ReferenceMonth::fromString($competencia));

        (new ExportadorDoRelatorioXlsx())->gravar($relatorio, $this->caminho);

        $reader = new Reader();
        $reader->open($this->caminho);

        $abas = [];
        foreach ($reader->getSheetIterator() as $aba) {
            $abas[$aba->getName()] = [];

            foreach ($aba->getRowIterator() as $linha) {
                $abas[$aba->getName()][] = $linha->toArray();
            }
        }

        $reader->close();

        return $abas;
    }

    /**
     * @param  list<list<mixed>>  $linhas
     * @return list<mixed>
     */
    private function linhaDe(array $linhas, string $rotulo, int $coluna = 0, int $ocorrencia = 1): array
    {
        foreach ($linhas as $linha) {
            if (($linha[$coluna] ?? null) === $rotulo && --$ocorrencia === 0) {
                return $linha;
            }
        }

        $this->fail("Linha \"$rotulo\" não encontrada na planilha.");
    }

    /**
     * @param  list<list<mixed>>  $linhas
     */
    private function valorDe(array $linhas, string $rotulo): mixed
    {
        return $this->linhaDe($linhas, $rotulo)[1];
    }

    /**
     * Competência 2026-07: casa (J), comum de cartão (D), dívida cruzada no
     * cartão de J e o pagamento da fatura de D.
     */
    private function mesMisto(): void
    {
        Transaction::query()->create($this->atributos([
            'transaction_date' => '2026-07-05',
            'description' => 'Aluguel',
            'amount' => '300.00',
            'who_paid' => 'J',
        ]));

        $cartaoD = $this->fatura('2026-07-11', 'D');
        $cartaoD->transactions()->create($this->atributos([
            'transaction_date' => '2026-06-12',
            'description' => 'Mercado',
        ]));
        $cartaoD->transactions()->create($this->atributos([
            'description' => 'Pagamento recebido',
            'amount' => '-500.00',
            'type' => 'pgto_de_fatura',
        ]));

        $cartaoJ = $this->fatura('2026-07-15', 'J');
        $cartaoJ->transactions()->create($this->atributos([
            'description' => 'Remedio do D',
            'amount' => '50.00',
            'who_paid' => 'J',
            'responsible_for_expense' => 'D',
            'common_expense' => false,
            'individual_expense' => true,
        ]));
    }

    private function fatura(string $vencimento, string $dono): CreditCardBill
    {
        return CreditCardBill::query()->create([
            'title_description_owner' => "Cartão $dono",
            'owner_bill' => $dono,
            'amount' => '0.00',
            'due_date' => $vencimento,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function atributos(array $atributos): array
    {
        return array_merge([
            'transaction_date' => '2026-06-15',
            'description' => 'Lançamento de teste',
            'amount' => '100.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
        ], $atributos);
    }
}
