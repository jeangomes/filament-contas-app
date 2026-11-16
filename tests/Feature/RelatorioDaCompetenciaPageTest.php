<?php

namespace Tests\Feature;

use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Filament\Pages\RelatorioDaCompetencia;
use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use App\Models\CreditCardBill;
use App\Models\MonthlySummary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FechamentoMensalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Renderizar de verdade é o que pega violação de `preventLazyLoading()` e
 * componente mal montado — os números em si são testados no domínio e em
 * `RelatorioDaCompetenciaServiceTest`.
 *
 * Sempre com o mês explícito na URL, para não depender da data do dia.
 */
final class RelatorioDaCompetenciaPageTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO_DE_DIVERGENCIA = 'O fechamento salvo está desatualizado';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function renderiza_competencia_sem_lancamentos(): void
    {
        $this->pagina('2026-07')
            ->assertOk()
            ->assertSee('Competência 07/2026 · gastos de 06/2026')
            ->assertSee('Sem acerto')
            ->assertSee('Ainda não fechado')
            ->assertSee('Nenhuma dívida cruzada nesta competência.')
            ->assertSee('Nenhuma despesa da casa nesta competência.')
            ->assertDontSee('fora do rateio, somando')
            ->assertDontSee(self::AVISO_DE_DIVERGENCIA);
    }

    #[Test]
    public function mostra_a_cota_e_quem_paga_quem(): void
    {
        $this->avulsa(['description' => 'Aluguel', 'amount' => '300.00', 'who_paid' => 'D']);

        $this->pagina('2026-07')
            ->assertOk()
            ->assertSee('Total comum de R$ 300,00 ÷ 2')
            ->assertSee('R$ 150,00')
            ->assertSee('J paga R$ 150,00 a D')
            ->assertSee('Aluguel')
            ->assertSee('soma dos saldos = zero: R$ 0,00 = R$ 0,00')
            ->assertSee('casa + comum dos cartões = total comum: R$ 300,00 = R$ 300,00')
            ->assertDontSee('≠')
            ->assertDontSee('o centavo que sobra');
    }

    #[Test]
    public function total_comum_impar_explica_com_quem_fica_o_centavo(): void
    {
        $this->avulsa(['description' => 'Mercado', 'amount' => '100.01', 'who_paid' => 'J']);

        $this->pagina('2026-07')
            ->assertSee('o centavo que sobra da divisão fica na cota de D')
            ->assertSee('R$ 50,01')
            ->assertSee('R$ 50,00');
    }

    #[Test]
    public function divida_cruzada_aparece_com_o_ajuste_de_cada_um(): void
    {
        $fatura = $this->fatura('2026-07-15', 'J');
        $this->deFatura($fatura, [
            'description' => 'Remedio do D',
            'amount' => '50.00',
            'who_paid' => 'J',
            'responsible_for_expense' => 'D',
            'common_expense' => false,
            'individual_expense' => true,
        ]);

        $this->pagina('2026-07')
            ->assertSee('Remedio do D')
            ->assertSee('Ajuste líquido')
            ->assertSee('-R$ 50,00')
            ->assertSee('D paga R$ 50,00 a J')
            ->assertDontSee('Nenhuma dívida cruzada nesta competência.')
            ->assertDontSee('≠');
    }

    #[Test]
    public function individual_avulsa_entra_na_origem_e_as_conferencias_fecham(): void
    {
        $this->avulsa(['description' => 'Aluguel', 'amount' => '300.00', 'who_paid' => 'J']);
        $this->avulsa([
            'description' => 'Livro',
            'amount' => '15.00',
            'who_paid' => 'J',
            'common_expense' => false,
            'individual_expense' => true,
        ]);
        $fatura = $this->fatura('2026-07-11', 'D');
        $this->deFatura($fatura, ['description' => 'Mercado', 'amount' => '100.00']);

        $this->pagina('2026-07')
            ->assertSee('Individuais avulsas')
            ->assertSee('R$ 15,00')
            ->assertSee('cartões + casa + individuais avulsas = total gasto: R$ 415,00 = R$ 415,00')
            ->assertSee('soma dos custos de vida = total gasto: R$ 415,00 = R$ 415,00')
            ->assertDontSee('≠');
    }

    #[Test]
    public function pagamento_de_fatura_e_avisado_como_fora_do_rateio(): void
    {
        $fatura = $this->fatura('2026-07-11', 'D');
        $this->deFatura($fatura, ['description' => 'Mercado', 'amount' => '100.00']);
        $this->deFatura($fatura, [
            'description' => 'Pagamento recebido',
            'amount' => '-500.00',
            'type' => 'pgto_de_fatura',
        ]);

        $this->pagina('2026-07')
            ->assertSee('1 lançamento(s) fora do rateio, somando -R$ 500,00')
            ->assertSee('Total comum de R$ 100,00 ÷ 2')
            ->assertDontSee('≠');
    }

    #[Test]
    public function mes_invalido_na_url_cai_no_mes_atual(): void
    {
        $this->pagina('julho')
            ->assertOk()
            ->assertSet('mes', now()->format('Y-m'));
    }

    #[Test]
    public function avisa_quando_o_fechamento_salvo_esta_desatualizado(): void
    {
        $this->avulsa(['description' => 'Aluguel', 'amount' => '300.00', 'who_paid' => 'D']);
        $this->fechar('2026-07');

        $this->pagina('2026-07')
            ->assertSee('Fechado em ' . now()->format('d/m/Y'))
            ->assertSee('acerto em aberto')
            ->assertDontSee(self::AVISO_DE_DIVERGENCIA);

        $this->avulsa(['description' => 'Condomínio', 'amount' => '80.00', 'who_paid' => 'D']);

        $this->pagina('2026-07')->assertSee(self::AVISO_DE_DIVERGENCIA);

        $this->fechar('2026-07');

        $this->pagina('2026-07')->assertDontSee(self::AVISO_DE_DIVERGENCIA);
    }

    #[Test]
    public function trocar_de_mes_troca_o_conteudo_e_nao_so_o_titulo(): void
    {
        $this->avulsa(['transaction_date' => '2026-07-05', 'description' => 'Aluguel de julho', 'amount' => '300.00']);
        $this->avulsa(['transaction_date' => '2026-08-05', 'description' => 'Aluguel de agosto', 'amount' => '420.00']);

        $this->pagina('2026-07')
            ->assertSee('Aluguel de julho')
            ->assertDontSee('Aluguel de agosto')
            ->callAction('proximo_mes')
            ->assertSet('mes', '2026-08')
            ->assertSee('Competência 08/2026 · gastos de 07/2026')
            ->assertSee('Aluguel de agosto')
            ->assertSee('Total comum de R$ 420,00 ÷ 2')
            ->assertDontSee('Aluguel de julho')
            ->callAction('mes_anterior')
            ->assertSet('mes', '2026-07')
            ->assertSee('Aluguel de julho');
    }

    #[Test]
    public function escolher_a_competencia_no_select_troca_o_conteudo(): void
    {
        $this->avulsa(['transaction_date' => '2026-07-05', 'description' => 'Aluguel de julho']);
        $this->avulsa(['transaction_date' => '2026-03-05', 'description' => 'Aluguel de março']);

        $this->pagina('2026-07')
            ->set('mes', '2026-03')
            ->assertSee('Competência 03/2026')
            ->assertSee('Aluguel de março')
            ->assertDontSee('Aluguel de julho');
    }

    #[Test]
    public function tabela_lista_todos_os_lancamentos_inclusive_o_pagamento_de_fatura(): void
    {
        $this->mesComUmDeCadaTipo();

        $this->pagina('2026-07')
            ->assertOk()
            ->assertSee('Lançamentos da competência')
            ->assertSee('Pagamento recebido')
            ->assertSee('Fora do rateio')
            ->assertSee('Dívida cruzada')
            ->assertSee('Comum (cartão)')
            ->assertSee('Cartão D · venc. 11/07/2026')
            ->assertSee('Avulso · Pix')
            ->assertSee('12/06/2026') // data da compra, não a do vencimento
            // 300 + 100 + 50 - 500 = -50,00; sem o pagamento de fatura, 450,00.
            ->assertSee('4 lançamento(s) · -R$ 50,00 · sem os fora do rateio: R$ 450,00');
    }

    #[Test]
    public function filtro_por_rubrica_responde_no_primeiro_clique_e_nao_mexe_no_resumo(): void
    {
        $this->mesComUmDeCadaTipo();

        $this->pagina('2026-07')
            ->assertSee('Mercado')
            // Mesma request, como o navegador manda: o valor do campo e o
            // clique em Aplicar. `filterTable()` não pegaria cache preso ao
            // filtro antigo.
            ->update(
                calls: [['method' => 'applyTableFilters', 'params' => [], 'path' => '']],
                updates: ['tableDeferredFilters.rubrica.values' => ['divida_cruzada']],
            )
            ->assertSee('1 lançamento(s) · R$ 50,00 · sem os fora do rateio: R$ 50,00')
            ->assertDontSee('Mercado')
            ->assertDontSee('Pagamento recebido')
            // As seções continuam do mês inteiro.
            ->assertSee('Total comum de R$ 400,00 ÷ 2')
            ->assertSee('Aluguel');
    }

    #[Test]
    public function filtros_de_pagador_e_origem_se_combinam(): void
    {
        $this->mesComUmDeCadaTipo();

        $this->pagina('2026-07')
            ->update(
                calls: [['method' => 'applyTableFilters', 'params' => [], 'path' => '']],
                updates: [
                    'tableDeferredFilters.pagou.value' => 'J',
                    'tableDeferredFilters.origem.value' => 'cartao',
                ],
            )
            // De J em cartão só a dívida cruzada; o Aluguel (J, avulso) sai.
            ->assertSee('1 lançamento(s) · R$ 50,00')
            ->assertDontSee('Mercado');
    }

    #[Test]
    public function busca_por_descricao_reduz_as_linhas_e_o_total(): void
    {
        $this->mesComUmDeCadaTipo();

        $this->pagina('2026-07')
            ->searchTable('merc')
            ->assertSee('1 lançamento(s) · R$ 100,00 · sem os fora do rateio: R$ 100,00')
            // "Remedio do D" continua na seção de dívidas cruzadas, que não é filtrada.
            ->assertDontSee('Pagamento recebido');
    }

    #[Test]
    public function tabela_mostra_parcela_e_renderiza_com_categoria_sem_lazy_loading(): void
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

        $this->pagina('2026-07')
            ->assertOk()
            ->assertSee('Decolar Com - Parcela 2/12')
            ->assertSee('Parcela 2/12');
    }

    #[Test]
    public function exporta_a_competencia_da_pagina_para_excel(): void
    {
        $this->mesComUmDeCadaTipo();

        $this->pagina('2026-07')
            ->callAction('exportar')
            ->assertFileDownloaded('relatorio-2026-07.xlsx');
    }

    #[Test]
    public function recalcular_pela_pagina_tira_o_aviso_e_preserva_o_que_o_usuario_marcou(): void
    {
        $this->avulsa(['description' => 'Aluguel', 'amount' => '300.00', 'who_paid' => 'D']);
        $this->fechar('2026-07');
        MonthlySummary::query()->firstOrFail()->update(['difference_paid' => true]);
        $this->avulsa(['description' => 'Condomínio', 'amount' => '80.00', 'who_paid' => 'D']);

        $this->pagina('2026-07')
            ->assertSee(self::AVISO_DE_DIVERGENCIA)
            ->assertActionHasLabel('recalcular', 'Recalcular fechamento')
            ->assertActionHasColor('recalcular', 'warning')
            ->callAction('recalcular')
            ->assertNotified()
            ->assertDontSee(self::AVISO_DE_DIVERGENCIA)
            ->assertActionHasColor('recalcular', 'gray');

        $salvo = MonthlySummary::query()->firstOrFail();

        $this->assertSame('380.00', $salvo->total_common_expenses);
        $this->assertTrue($salvo->difference_paid);
    }

    #[Test]
    public function competencia_nao_fechada_pode_ser_calculada_pela_pagina(): void
    {
        $this->avulsa(['description' => 'Aluguel', 'amount' => '300.00', 'who_paid' => 'D']);

        $this->pagina('2026-07')
            ->assertSee('Ainda não fechado')
            ->assertActionHasLabel('recalcular', 'Calcular fechamento')
            ->assertActionHidden('abrir_fechamento')
            ->callAction('recalcular')
            ->assertDontSee('Ainda não fechado')
            ->assertActionVisible('abrir_fechamento')
            ->assertActionHasUrl(
                'abrir_fechamento',
                MonthlySummaryResource::getUrl('view', ['record' => MonthlySummary::query()->firstOrFail()]),
            );

        $this->assertSame('2026-07-01', MonthlySummary::query()->firstOrFail()->competencia->format('Y-m-d'));
    }

    #[Test]
    public function tabela_vazia_quando_a_competencia_nao_tem_lancamentos(): void
    {
        $this->pagina('2026-07')
            ->assertSee('Nenhum lançamento encontrado.')
            ->assertSee('0 lançamento(s) · R$ 0,00');
    }

    /**
     * Casa (J, avulso com origem), comum de cartão (D), dívida cruzada no
     * cartão de J e o pagamento da fatura de D.
     */
    private function mesComUmDeCadaTipo(): void
    {
        $this->avulsa(['description' => 'Aluguel', 'amount' => '300.00', 'who_paid' => 'J', 'origin' => 'Pix']);

        $cartaoD = $this->fatura('2026-07-11', 'D');
        $this->deFatura($cartaoD, ['transaction_date' => '2026-06-12', 'description' => 'Mercado', 'amount' => '100.00']);
        $this->deFatura($cartaoD, [
            'description' => 'Pagamento recebido',
            'amount' => '-500.00',
            'type' => 'pgto_de_fatura',
        ]);

        $cartaoJ = $this->fatura('2026-07-15', 'J');
        $this->deFatura($cartaoJ, [
            'description' => 'Remedio do D',
            'amount' => '50.00',
            'who_paid' => 'J',
            'responsible_for_expense' => 'D',
            'common_expense' => false,
            'individual_expense' => true,
        ]);
    }

    private function pagina(string $mes): Testable
    {
        return Livewire::withQueryParams(['mes' => $mes])->test(RelatorioDaCompetencia::class);
    }

    private function fechar(string $competencia): void
    {
        app(FechamentoMensalService::class)->apurarEPersistir(ReferenceMonth::fromString($competencia));
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
        return $fatura->transactions()->create($this->atributos(['transaction_date' => '2026-06-15', ...$atributos]));
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
            'transaction_date' => '2026-07-05',
            'description' => 'Lançamento de teste',
            'amount' => '100.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
        ], $atributos);
    }
}
