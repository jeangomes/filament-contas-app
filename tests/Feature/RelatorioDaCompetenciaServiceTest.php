<?php

namespace Tests\Feature;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\Rubrica;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\FechamentoMensalService;
use App\Services\Relatorio\LinhaDoRelatorio;
use App\Services\Relatorio\RelatorioDaCompetencia;
use App\Services\RelatorioDaCompetenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RelatorioDaCompetenciaServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function classifica_cada_lancamento_e_soma_o_total_gasto_sem_o_pagamento_de_fatura(): void
    {
        $this->mesMisto();

        $relatorio = $this->gerar('2026-07');

        $this->assertCount(6, $relatorio->linhas);
        $this->assertSame(['Aluguel'], $this->descricoes($relatorio->linhasDa(Rubrica::ComumDaCasa)));
        $this->assertSame(['Mercado'], $this->descricoes($relatorio->linhasDa(Rubrica::ComumDeCartao)));
        $this->assertSame(['Academia', 'Livro'], $this->descricoes($relatorio->linhasDa(Rubrica::IndividualPropria)));
        $this->assertSame(['Remedio do D'], $this->descricoes($relatorio->linhasDa(Rubrica::DividaCruzada)));
        $this->assertSame(['Pagamento recebido'], $this->descricoes($relatorio->linhasDa(Rubrica::ForaDoRateio)));

        // 300 + 100 + 40 + 15 + 50 = 505,00 — os -500,00 do pagamento ficam de fora.
        $this->assertTrue($relatorio->totalGasto()->equals(Money::emCentavos(50500)));
        $this->assertTrue($relatorio->totalDa(Rubrica::ForaDoRateio)->equals(Money::emCentavos(-50000)));

        // Individuais por responsável: D = 40 (Academia) + 50 (adiantado por J); J = 15 (Livro).
        $this->assertTrue($relatorio->individuaisDe(Participant::D)->equals(Money::emCentavos(9000)));
        $this->assertTrue($relatorio->individuaisDe(Participant::J)->equals(Money::emCentavos(1500)));
        $this->assertTrue($relatorio->individuaisAvulsas()->equals(Money::emCentavos(1500)));
    }

    #[Test]
    public function as_somas_das_linhas_fecham_com_o_fechamento_do_dominio(): void
    {
        $this->mesMisto();

        $relatorio = $this->gerar('2026-07');
        $fechamento = $relatorio->fechamento;

        $somaDosCustos = Money::zero();
        $cartoes = Money::zero();

        foreach ($fechamento->participantes() as $participante) {
            $somaDosCustos = $somaDosCustos->somar($participante->custoDeVida);
            $cartoes = $cartoes->somar($participante->cartaoTotal);

            $this->assertTrue(
                $participante->cotaComum
                    ->somar($relatorio->individuaisDe($participante->participante))
                    ->equals($participante->custoDeVida),
            );
        }

        $this->assertTrue($somaDosCustos->equals($relatorio->totalGasto()));
        $this->assertTrue(
            $cartoes->somar($fechamento->totalCasa())
                ->somar($relatorio->individuaisAvulsas())
                ->equals($relatorio->totalGasto()),
        );
        $this->assertTrue(
            $relatorio->totalDa(Rubrica::ComumDaCasa)
                ->somar($relatorio->totalDa(Rubrica::ComumDeCartao))
                ->equals($fechamento->totalComum()),
        );
    }

    #[Test]
    public function item_de_fatura_entra_na_competencia_do_vencimento_com_a_data_original_da_compra(): void
    {
        $fatura = $this->fatura('2026-07-11', 'D');
        $this->deFatura($fatura, ['transaction_date' => '2026-06-20', 'description' => 'Mercado']);

        $this->assertCount(0, $this->gerar('2026-06')->linhas);

        $linha = $this->gerar('2026-07')->linhas[0];

        $this->assertSame('2026-06-20', $linha->dataDaCompra->format('Y-m-d'));
        $this->assertSame('2026-07', $linha->lancamento->referenceMonth()->chave());
        $this->assertSame($fatura->id, $linha->faturaId);
        $this->assertSame('Cartão D · venc. 11/07/2026', $linha->fatura);
    }

    #[Test]
    public function linha_carrega_parcela_categoria_e_origem_quando_existem(): void
    {
        $categoria = DB::table('expense_categories')->insertGetId(['description' => 'Viagem']);

        $fatura = $this->fatura('2026-07-11', 'J');
        $this->deFatura($fatura, [
            'description' => 'Decolar Com - Parcela 2/12',
            'installment_current' => 2,
            'installment_total' => 12,
            'expense_category_id' => $categoria,
            'who_paid' => 'J',
        ]);
        $this->avulsa(['transaction_date' => '2026-07-20', 'description' => 'Feira', 'origin' => 'Pix']);

        [$parcelada, $avulsa] = $this->gerar('2026-07')->linhas;

        $this->assertSame('2/12', $parcelada->parcela);
        $this->assertSame('Viagem', $parcelada->categoria);

        $this->assertNull($avulsa->faturaId);
        $this->assertNull($avulsa->fatura);
        $this->assertNull($avulsa->parcela);
        $this->assertNull($avulsa->categoria);
        $this->assertSame('Pix', $avulsa->origem);
    }

    #[Test]
    public function fechamento_recem_calculado_nao_diverge_e_passa_a_divergir_com_lancamento_novo(): void
    {
        $this->mesMisto();
        app(FechamentoMensalService::class)->apurarEPersistir(ReferenceMonth::fromString('2026-07'));

        $relatorio = $this->gerar('2026-07');

        $this->assertNotNull($relatorio->persistido);
        $this->assertFalse($relatorio->divergeDoPersistido());

        $this->avulsa(['transaction_date' => '2026-07-25', 'description' => 'Condomínio', 'amount' => '80.00']);

        $this->assertTrue($this->gerar('2026-07')->divergeDoPersistido());
    }

    #[Test]
    public function individual_nova_muda_o_custo_de_vida_e_tambem_diverge(): void
    {
        $this->mesMisto();
        app(FechamentoMensalService::class)->apurarEPersistir(ReferenceMonth::fromString('2026-07'));

        // Não mexe em total comum, casa nem acerto — só no custo de vida de J.
        $this->avulsa([
            'transaction_date' => '2026-07-26',
            'description' => 'Cinema',
            'amount' => '30.00',
            'who_paid' => 'J',
            'common_expense' => false,
            'individual_expense' => true,
        ]);

        $this->assertTrue($this->gerar('2026-07')->divergeDoPersistido());
    }

    #[Test]
    public function sem_fechamento_salvo_nao_ha_divergencia(): void
    {
        $this->mesMisto();

        $relatorio = $this->gerar('2026-07');

        $this->assertNull($relatorio->persistido);
        $this->assertFalse($relatorio->divergeDoPersistido());
    }

    #[Test]
    public function mes_sem_lancamentos_gera_relatorio_zerado(): void
    {
        $relatorio = $this->gerar('2026-07');

        $this->assertSame([], $relatorio->linhas);
        $this->assertTrue($relatorio->totalGasto()->ehZero());
        $this->assertTrue($relatorio->fechamento->totalComum()->ehZero());
        $this->assertSame([], $relatorio->fechamento->acertos());
    }

    /**
     * Competência 2026-07 com uma linha de cada rubrica (duas individuais
     * próprias: uma de cartão, uma avulsa).
     */
    private function mesMisto(): void
    {
        $this->avulsa(['transaction_date' => '2026-07-05', 'description' => 'Aluguel', 'amount' => '300.00', 'who_paid' => 'J']);
        $this->avulsa([
            'transaction_date' => '2026-07-06',
            'description' => 'Livro',
            'amount' => '15.00',
            'who_paid' => 'J',
            'common_expense' => false,
            'individual_expense' => true,
        ]);

        $cartaoD = $this->fatura('2026-07-11', 'D');
        $this->deFatura($cartaoD, ['transaction_date' => '2026-06-12', 'description' => 'Mercado', 'amount' => '100.00']);
        $this->deFatura($cartaoD, [
            'transaction_date' => '2026-06-13',
            'description' => 'Academia',
            'amount' => '40.00',
            'common_expense' => false,
            'individual_expense' => true,
        ]);
        $this->deFatura($cartaoD, [
            'transaction_date' => '2026-06-10',
            'description' => 'Pagamento recebido',
            'amount' => '-500.00',
            'type' => 'pgto_de_fatura',
        ]);

        $cartaoJ = $this->fatura('2026-07-15', 'J');
        $this->deFatura($cartaoJ, [
            'transaction_date' => '2026-06-14',
            'description' => 'Remedio do D',
            'amount' => '50.00',
            'who_paid' => 'J',
            'responsible_for_expense' => 'D',
            'common_expense' => false,
            'individual_expense' => true,
        ]);
    }

    private function gerar(string $competencia): RelatorioDaCompetencia
    {
        return app(RelatorioDaCompetenciaService::class)->gerar(ReferenceMonth::fromString($competencia));
    }

    /**
     * @param  list<LinhaDoRelatorio>  $linhas
     * @return list<string>
     */
    private function descricoes(array $linhas): array
    {
        $descricoes = array_map(
            static fn (LinhaDoRelatorio $linha): string => $linha->lancamento->description(),
            $linhas,
        );
        sort($descricoes);

        return $descricoes;
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
     */
    private function deFatura(CreditCardBill $fatura, array $atributos): Transaction
    {
        // Pela relação: credit_card_bill_id não está no $fillable.
        return $fatura->transactions()->create($this->atributos($atributos));
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function avulsa(array $atributos): Transaction
    {
        return Transaction::query()->create($this->atributos($atributos));
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
