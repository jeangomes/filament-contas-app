<?php

namespace Tests\Feature;

use App\Filament\Pages\BillsSideBySideOverview;
use App\Filament\Pages\Widgets\LancamentosDaFatura;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Faturas Mensais": a página só cuida do mês; cada fatura é um
 * {@see LancamentosDaFatura}. Renderizar de verdade é o que pega violação de
 * `preventLazyLoading()` e coluna inexistente.
 */
final class BillsSideBySideOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function renderiza_sem_faturas_no_mes(): void
    {
        Livewire::test(BillsSideBySideOverview::class)->assertOk();

        Livewire::test(LancamentosDaFatura::class, ['dono' => 'D', 'mes' => now()->format('Y-m')])
            ->assertOk()
            ->assertSee('Nenhuma fatura encontrada para este mês.')
            ->assertSee('nenhuma fatura neste mês');
    }

    #[Test]
    public function abre_no_mes_atual_e_mostra_os_dois_participantes(): void
    {
        Livewire::test(BillsSideBySideOverview::class)
            ->assertSet('mes', now()->format('Y-m'))
            ->assertSee('Vencimento em ' . now()->format('m/Y'))
            ->assertSeeLivewire(LancamentosDaFatura::class);
    }

    #[Test]
    public function os_totais_saem_das_transacoes_mesmo_com_o_cache_zerado(): void
    {
        // Criar pela relação não dispara o recálculo do observer (só roda no
        // `updated`): common_amount/individual_amount ficam em zero, como
        // logo após uma importação. A página antiga mostrava R$ 0,00 aqui.
        $fatura = $this->fatura('J', '2026-07-10');
        $this->lancamento($fatura, 'Mercado', '150.00', comum: true);
        $this->lancamento($fatura, 'Farmácia', '40.00', comum: false);

        $this->assertSame('0.00', $fatura->fresh()->common_amount);

        Livewire::test(LancamentosDaFatura::class, ['dono' => 'J', 'mes' => '2026-07'])
            ->assertOk()
            ->assertSee('vencimento 10/07/2026')
            ->assertSee('Mercado')
            ->assertSee('Farmácia')
            ->assertSee('190,00')   // total
            ->assertSee('150,00')   // comuns
            ->assertSee('40,00');   // individuais
    }

    #[Test]
    public function pagamento_de_fatura_aparece_mas_nao_entra_no_total(): void
    {
        $fatura = $this->fatura('D', '2026-07-10');
        $this->lancamento($fatura, 'Mercado', '150.00', comum: true);
        $this->lancamento($fatura, 'Pagamento recebido', '-999.00', comum: false, tipo: 'pgto_de_fatura');

        Livewire::test(LancamentosDaFatura::class, ['dono' => 'D', 'mes' => '2026-07'])
            ->assertSee('Pagamento recebido')
            ->assertSee('150,00')
            ->assertDontSee('-849,00');
    }

    #[Test]
    public function so_mostra_a_fatura_do_dono_e_do_mes(): void
    {
        $this->lancamento($this->fatura('D', '2026-07-10'), 'Do D em julho', '10.00', comum: true);
        $this->lancamento($this->fatura('J', '2026-07-10'), 'Do J em julho', '20.00', comum: true);
        $this->lancamento($this->fatura('D', '2026-08-10'), 'Do D em agosto', '30.00', comum: true);

        Livewire::test(LancamentosDaFatura::class, ['dono' => 'D', 'mes' => '2026-07'])
            ->assertSee('Do D em julho')
            ->assertDontSee('Do J em julho')
            ->assertDontSee('Do D em agosto');
    }

    #[Test]
    public function navega_para_o_mes_anterior_e_o_proximo_inclusive_virada_de_ano(): void
    {
        Livewire::withQueryParams(['mes' => '2026-01'])
            ->test(BillsSideBySideOverview::class)
            ->assertSet('mes', '2026-01')
            ->callAction('mes_anterior')
            ->assertSet('mes', '2025-12')
            ->callAction('proximo_mes')
            ->callAction('proximo_mes')
            ->assertSet('mes', '2026-02')
            ->assertSee('Vencimento em 02/2026');
    }

    #[Test]
    public function escolher_o_mes_no_select_troca_o_mes_na_mesma_request(): void
    {
        Livewire::withQueryParams(['mes' => '2026-07'])
            ->test(BillsSideBySideOverview::class)
            ->set('mes', '2026-03')
            ->assertSet('mes', '2026-03')
            ->assertSee('Vencimento em 03/2026');
    }

    #[Test]
    public function mes_invalido_na_url_cai_no_mes_atual(): void
    {
        Livewire::withQueryParams(['mes' => '2026-13'])
            ->test(BillsSideBySideOverview::class)
            ->assertOk()
            ->assertSet('mes', now()->format('Y-m'));
    }

    #[Test]
    public function o_widget_nao_faz_consulta_por_lancamento(): void
    {
        $fatura = $this->fatura('J', '2026-07-10');
        $this->lancamento($fatura, 'Primeiro', '10.00', comum: true);
        $comUm = $this->consultasParaRenderizar();

        foreach (range(1, 15) as $i) {
            $this->lancamento($fatura, "Compra {$i}", '10.00', comum: $i % 2 === 0);
        }

        $this->assertSame($comUm, $this->consultasParaRenderizar());
    }

    private function consultasParaRenderizar(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(LancamentosDaFatura::class, ['dono' => 'J', 'mes' => '2026-07'])->assertOk();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    private function fatura(string $dono, string $vencimento): CreditCardBill
    {
        return CreditCardBill::query()->create([
            'title_description_owner' => "Fatura {$dono}",
            'owner_bill' => $dono,
            'amount' => '0.00',
            'due_date' => $vencimento,
        ]);
    }

    private function lancamento(
        CreditCardBill $fatura,
        string $descricao,
        string $valor,
        bool $comum,
        string $tipo = 'variable_expense',
    ): Transaction {
        return $fatura->transactions()->create([
            'transaction_date' => $fatura->due_date->copy()->subDays(5)->format('Y-m-d'),
            'description' => $descricao,
            'amount' => $valor,
            'common_expense' => $comum,
            'individual_expense' => ! $comum,
            'who_paid' => $fatura->owner_bill,
            'type' => $tipo,
        ]);
    }
}
