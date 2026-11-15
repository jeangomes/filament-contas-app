<?php

namespace Tests\Feature;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Filament\Resources\MonthlySummaries\MonthlySummaryResource;
use App\Filament\Resources\MonthlySummaries\Pages\ListMonthlySummaries;
use App\Filament\Resources\MonthlySummaries\Pages\ViewMonthlySummary;
use App\Filament\Resources\MonthlySummaries\Widgets\UltimoFechamento;
use App\Models\MonthlySummary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FechamentoMensalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O Consolidado Mensal só lê o que foi persistido, mas lê através de relações e
 * acessores — e o projeto roda com preventLazyLoading()/
 * preventAccessingMissingAttributes(). Renderizar de verdade é o que pega
 * coluna que sumiu e consulta faltando.
 *
 * Atenção ao alcance: Livewire::test() monta o componente direto, sem passar
 * pelo middleware do painel. Estes testes cobrem renderização e ações, NÃO a
 * autorização de acesso (que é do painel, via canAccessPanel).
 */
final class MonthlySummaryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function renderiza_com_a_tabela_vazia(): void
    {
        Livewire::test(ListMonthlySummaries::class)->assertOk();
    }

    #[Test]
    public function renderiza_com_historico_e_mostra_os_valores_apurados(): void
    {
        $this->fecharUmMesComDados();

        Livewire::test(ListMonthlySummaries::class)
            ->assertOk()
            ->assertSee('07/2026')          // competência
            ->assertSee('D deve pagar');    // acerto, via acessor do model
    }

    #[Test]
    public function a_acao_de_calcular_persiste_a_competencia(): void
    {
        Transaction::query()->create($this->atributos());

        Livewire::test(ListMonthlySummaries::class)
            ->callAction('calcular_mes', ['competencia' => '2026-07'])
            ->assertHasNoActionErrors();

        $this->assertSame('2026-07-01', MonthlySummary::query()->sole()->competencia->format('Y-m-d'));
    }

    #[Test]
    public function recalcular_tudo_preserva_o_que_o_usuario_marcou(): void
    {
        $this->fecharUmMesComDados();
        MonthlySummary::query()->sole()->update(['difference_paid' => true]);

        Livewire::test(ListMonthlySummaries::class)
            ->callAction('recalcular_tudo')
            ->assertHasNoActionErrors();

        $this->assertSame(1, MonthlySummary::query()->count());
        $this->assertTrue(MonthlySummary::query()->sole()->difference_paid);
    }

    #[Test]
    public function a_tabela_mostra_o_custo_de_vida_de_cada_participante(): void
    {
        $resumo = $this->fecharUmMesComDados();

        $custoDeD = $resumo->participantSummary(Participant::D)->living_cost;

        Livewire::test(ListMonthlySummaries::class)
            ->assertOk()
            ->assertSee(number_format((float) $custoDeD, 2, ',', '.'));
    }

    #[Test]
    public function a_tabela_nao_faz_consulta_por_linha(): void
    {
        // Com eager load, passar de 1 para 6 competências não pode aumentar o
        // número de consultas proporcionalmente.
        $this->fecharMeses(['2026-07']);
        $consultasComUmMes = $this->consultasParaRenderizar();

        $this->fecharMeses(['2026-02', '2026-03', '2026-04', '2026-05', '2026-06']);
        $consultasComSeisMeses = $this->consultasParaRenderizar();

        $this->assertLessThanOrEqual(
            $consultasComUmMes + 1,
            $consultasComSeisMeses,
            "Renderizar 6 competências custou {$consultasComSeisMeses} consultas contra "
            . "{$consultasComUmMes} para uma — sinal de consulta por linha.",
        );
    }

    #[Test]
    public function o_detalhe_mostra_participantes_e_totais(): void
    {
        $resumo = $this->fecharUmMesComDados();
        $custoDeJ = $resumo->participantSummary(Participant::J)->living_cost;

        // Rota de verdade do route binding: getEloquentQuery() precisa trazer
        // as relações, senão preventLazyLoading() estoura aqui.
        Livewire::test(ViewMonthlySummary::class, ['record' => $resumo->getKey()])
            ->assertOk()
            ->assertSee('Fechamento de 07/2026')
            ->assertSee('Por participante')
            ->assertSee('Despesas da casa')
            ->assertSee(number_format((float) $custoDeJ, 2, ',', '.'));
    }

    #[Test]
    public function o_widget_mostra_a_competencia_mais_recente(): void
    {
        $this->fecharMeses(['2026-06', '2026-07']);

        Livewire::test(UltimoFechamento::class)
            ->assertOk()
            ->assertSee('Última competência')
            ->assertSee('07/2026');
    }

    #[Test]
    public function sem_fechamento_o_widget_fica_vazio(): void
    {
        Livewire::test(UltimoFechamento::class)
            ->assertOk()
            ->assertDontSee('Última competência');
    }

    #[Test]
    public function fechamento_nao_se_cria_nem_se_edita_pela_tela(): void
    {
        $resumo = $this->fecharUmMesComDados();

        $this->assertFalse(MonthlySummaryResource::canCreate());
        $this->assertFalse(MonthlySummaryResource::canEdit($resumo));
        $this->assertFalse(MonthlySummaryResource::canDelete($resumo));
    }

    #[Test]
    public function calcular_avisa_o_widget_para_atualizar(): void
    {
        Transaction::query()->create($this->atributos());

        Livewire::test(ListMonthlySummaries::class)
            ->callAction('calcular_mes', ['competencia' => '2026-07'])
            ->assertDispatched(UltimoFechamento::EVENTO_ATUALIZADO);
    }

    #[Test]
    public function como_funciona_abre_a_explicacao(): void
    {
        Livewire::test(ListMonthlySummaries::class)
            ->mountAction('como_funciona')
            ->assertActionMounted('como_funciona')
            ->assertMountedActionModalSee(['Custo de vida × saldo', 'não se somam']);
    }

    private function consultasParaRenderizar(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListMonthlySummaries::class)->assertOk();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    /**
     * @param  list<string>  $competencias
     */
    private function fecharMeses(array $competencias): void
    {
        foreach ($competencias as $competencia) {
            Transaction::query()->create(array_merge($this->atributos(), [
                'transaction_date' => $competencia . '-15',
            ]));

            app(FechamentoMensalService::class)
                ->apurarEPersistir(ReferenceMonth::fromString($competencia));
        }
    }

    private function fecharUmMesComDados(): MonthlySummary
    {
        Transaction::query()->create($this->atributos());

        return app(FechamentoMensalService::class)
            ->apurarEPersistir(ReferenceMonth::fromString('2026-07'));
    }

    /**
     * @return array<string, mixed>
     */
    private function atributos(): array
    {
        return [
            'transaction_date' => '2026-07-15',
            'description' => 'Aluguel',
            'amount' => '300.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'J',
            'type' => 'fixed_expense',
        ];
    }
}
