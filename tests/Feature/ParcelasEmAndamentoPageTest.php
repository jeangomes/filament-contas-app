<?php

namespace Tests\Feature;

use App\Filament\Pages\ParcelasEmAndamento;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Renderizar de verdade é o que pega violação de `preventLazyLoading()` e
 * coluna inexistente — a apuração em si é testada no domínio.
 */
final class ParcelasEmAndamentoPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function renderiza_sem_nenhuma_parcela(): void
    {
        Livewire::test(ParcelasEmAndamento::class)
            ->assertOk()
            ->assertSee('Nenhuma compra parcelada encontrada.');
    }

    #[Test]
    public function lista_a_serie_em_andamento_com_o_que_falta(): void
    {
        $fatura = $this->fatura('2026-08-11', 'J');
        $this->parcela($fatura, 'Decolar Com', 2, 12, '908.10');

        Livewire::test(ParcelasEmAndamento::class)
            ->assertOk()
            ->assertSee('Decolar Com')
            ->assertSee('2/12')
            ->assertSee('R$ 9.081,00')  // 10 parcelas restantes
            ->assertSee('06/2027');     // 08/2026 é a parcela 2; a 12 cai 10 meses depois
    }

    #[Test]
    public function serie_quitada_so_aparece_com_o_filtro_ligado(): void
    {
        $fatura = $this->fatura('2026-02-11', 'D');
        $this->parcela($fatura, 'Casa e Video', 3, 3, '30.99');

        Livewire::test(ParcelasEmAndamento::class)
            ->assertOk()
            ->assertDontSee('Casa e Video')
            ->set('incluirQuitadas', true)
            ->assertSee('Casa e Video');
    }

    #[Test]
    public function filtra_por_pagador(): void
    {
        $this->parcela($this->fatura('2026-08-11', 'J'), 'Decolar Com', 2, 12, '908.10');
        $this->parcela($this->fatura('2026-08-14', 'D'), 'Dl*Starlink Braz', 7, 8, '210.00');

        Livewire::test(ParcelasEmAndamento::class)
            ->assertOk()
            ->assertSee('Decolar Com')
            ->assertSee('Dl*Starlink Braz')
            ->set('pagador', 'D')
            ->assertDontSee('Decolar Com')
            ->assertSee('Dl*Starlink Braz');
    }

    #[Test]
    public function serie_sem_lancamento_recente_avisa_em_vez_de_projetar_o_passado(): void
    {
        // Fatura de dois anos atrás com a parcela 2/4: as 3 e 4 nunca foram
        // importadas, então "termina em" cairia no passado.
        $vencimento = now()->subYears(2)->format('Y-m') . '-11';
        $this->parcela($this->fatura($vencimento, 'J'), 'Renner', 2, 4, '70.92');

        Livewire::test(ParcelasEmAndamento::class)
            ->assertOk()
            ->assertSee('Renner')
            ->assertSee('sem lançamento desde')
            ->assertSee('Nenhuma parcela a vencer.');
    }

    #[Test]
    public function a_pagina_nao_faz_consulta_por_serie(): void
    {
        // A competência vem do vencimento da fatura: sem eager load seria uma
        // consulta por lançamento (e `preventLazyLoading()` derrubaria a página).
        foreach (range(1, 5) as $indice) {
            $fatura = $this->fatura(sprintf('2026-%02d-11', $indice), 'J');
            $this->parcela($fatura, "Compra {$indice}", 2, 10, '100.00');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ParcelasEmAndamento::class)->assertOk();

        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Uma para os lançamentos, uma para as faturas do eager load, e as do
        // usuário autenticado — nada que cresça com o número de séries.
        $this->assertLessThan(10, $consultas);
    }

    private function fatura(string $vencimento, string $dono): CreditCardBill
    {
        return CreditCardBill::query()->create([
            'title_description_owner' => "Fatura {$dono}",
            'owner_bill' => $dono,
            'amount' => '0.00',
            'due_date' => $vencimento,
        ]);
    }

    private function parcela(
        CreditCardBill $fatura,
        string $serie,
        int $atual,
        int $total,
        string $valor,
    ): Transaction {
        return $fatura->transactions()->create([
            'transaction_date' => $fatura->due_date->copy()->subDays(7)->format('Y-m-d'),
            'description' => "{$serie} - Parcela {$atual}/{$total}",
            'amount' => $valor,
            'common_expense' => false,
            'individual_expense' => true,
            'who_paid' => $fatura->owner_bill,
            'type' => 'variable_expense',
            'installment_current' => $atual,
            'installment_total' => $total,
        ]);
    }
}
