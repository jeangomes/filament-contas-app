<?php

namespace Tests\Feature;

use App\Filament\Pages\RelatorioDaCompetencia;
use App\Filament\Pages\VisaoAnual;
use App\Models\MonthlySummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Visão Anual": lê só o fechamento persistido. Os fechamentos são criados
 * direto pelos models — o cálculo em si é testado no domínio e em
 * `FechamentoMensalServiceTest`.
 */
final class VisaoAnualPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function abre_no_ano_atual(): void
    {
        Livewire::test(VisaoAnual::class)
            ->assertOk()
            ->assertSet('ano', (string) now()->year)
            ->assertSee('Nenhuma competência fechada em ' . now()->year)
            ->assertSee('Sem fechamento');
    }

    #[Test]
    public function mostra_os_quatro_valores_de_cada_participante(): void
    {
        $this->fechar('2026-07-01', [
            'D' => ['card_total' => '1234.56', 'card_common' => '1000.00', 'card_individual' => '234.56', 'living_cost' => '2100.10'],
            'J' => ['card_total' => '987.65', 'card_common' => '900.00', 'card_individual' => '87.65', 'living_cost' => '1800.20'],
        ]);

        $this->pagina('2026')
            ->assertOk()
            ->assertSee('07/2026')
            ->assertSee('gastos de 06/2026')
            ->assertSee('R$ 1.234,56')
            ->assertSee('R$ 234,56')
            ->assertSee('R$ 2.100,10')
            ->assertSee('R$ 987,65')
            ->assertSee('R$ 87,65')
            ->assertSee('R$ 1.800,20');
    }

    #[Test]
    public function avisa_os_meses_sem_competencia(): void
    {
        $this->fechar('2026-07-01', ['D' => ['card_total' => '10.00'], 'J' => ['card_total' => '20.00']]);

        $this->pagina('2026')
            ->assertSee('1 de 12 competências fechadas — sem fechamento: 01, 02, 03, 04, 05, 06, 08, 09, 10, 11, 12.')
            ->assertSee('Sem fechamento');
    }

    #[Test]
    public function ano_completo_nao_lista_faltantes(): void
    {
        foreach (range(1, 12) as $mes) {
            $this->fechar(sprintf('2026-%02d-01', $mes), ['D' => [], 'J' => []]);
        }

        $this->pagina('2026')
            ->assertSee('12 de 12 competências fechadas.')
            ->assertDontSee('Sem fechamento');
    }

    #[Test]
    public function total_do_ano_soma_apenas_meses_fechados(): void
    {
        $this->fechar('2026-03-01', ['D' => ['living_cost' => '1000.01'], 'J' => []]);
        $this->fechar('2026-04-01', ['D' => ['living_cost' => '2000.02'], 'J' => []]);

        $this->pagina('2026')
            ->assertSee('Total do ano')
            ->assertSee('R$ 3.000,03');
    }

    #[Test]
    public function navega_entre_anos(): void
    {
        $this->fechar('2026-07-01', ['D' => ['card_total' => '555.55'], 'J' => []]);
        $this->fechar('2025-07-01', ['D' => ['card_total' => '444.44'], 'J' => []]);

        $this->pagina('2026')
            ->assertSee('R$ 555,55')
            ->callAction('ano_anterior')
            ->assertSet('ano', '2025')
            ->assertSee('R$ 444,44')
            ->assertDontSee('R$ 555,55')
            ->callAction('proximo_ano')
            ->callAction('proximo_ano')
            ->assertSet('ano', '2027')
            ->assertSee('Nenhuma competência fechada em 2027');
    }

    #[Test]
    public function ano_invalido_na_url_cai_no_atual(): void
    {
        $this->pagina('abc')
            ->assertOk()
            ->assertSet('ano', (string) now()->year);
    }

    #[Test]
    public function nao_mostra_fechamento_de_outro_ano(): void
    {
        $this->fechar('2025-12-01', ['D' => ['card_total' => '111.11'], 'J' => []]);
        $this->fechar('2027-01-01', ['D' => ['card_total' => '222.22'], 'J' => []]);

        $this->pagina('2026')
            ->assertSee('Nenhuma competência fechada em 2026')
            ->assertDontSee('R$ 111,11')
            ->assertDontSee('R$ 222,22');
    }

    #[Test]
    public function participante_sem_linha_no_fechamento_fica_vazio_so_nas_colunas_dele(): void
    {
        $this->fechar('2026-07-01', ['D' => ['card_total' => '321.00']]);

        $this->pagina('2026')
            ->assertOk()
            ->assertSee('R$ 321,00')
            ->assertSee('1 de 12 competências fechadas');
    }

    #[Test]
    public function mes_fechado_leva_ao_relatorio_da_competencia(): void
    {
        $this->fechar('2026-07-01', ['D' => [], 'J' => []]);

        $this->pagina('2026')
            ->assertSee(RelatorioDaCompetencia::getUrl(['mes' => '2026-07']), escape: false);
    }

    private function pagina(string $ano): Testable
    {
        return Livewire::withQueryParams(['ano' => $ano])->test(VisaoAnual::class);
    }

    /** @param array<string, array<string, string>> $porParticipante */
    private function fechar(string $competencia, array $porParticipante): void
    {
        $resumo = MonthlySummary::query()->create(['competencia' => $competencia]);

        foreach ($porParticipante as $participante => $valores) {
            $resumo->participantSummaries()->create(['participant' => $participante, ...$valores]);
        }
    }
}
