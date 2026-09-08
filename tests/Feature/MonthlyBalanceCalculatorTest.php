<?php

namespace Tests\Feature;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Services\MonthlyBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exercita o caminho completo de produção: banco -> repositório -> mapper ->
 * calculadora de domínio -> formato de array consumido pela página
 * ComputedResults.
 */
final class MonthlyBalanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function apura_o_saldo_do_mes_no_formato_esperado_pela_pagina_de_relatorio(): void
    {
        // D pagou 200,00 e J pagou 100,00 de despesas comuns: total 300,00,
        // cota de 150,00 para cada um.
        $this->lancamento(['who_paid' => 'D', 'amount' => '200.00']);
        $this->lancamento(['who_paid' => 'J', 'amount' => '100.00']);

        $balances = app(MonthlyBalanceCalculator::class)->calculateFinalBalances();

        $this->assertCount(2, $balances);

        $porParticipante = collect($balances)->keyBy('participant');

        $this->assertSame('2026-01', $porParticipante['D']['month_year']);
        $this->assertSame(200.0, $porParticipante['D']['total_paid_common']);
        $this->assertSame(150.0, $porParticipante['D']['share_common']);
        $this->assertSame(50.0, $porParticipante['D']['balance']);

        $this->assertSame(100.0, $porParticipante['J']['total_paid_common']);
        $this->assertSame(150.0, $porParticipante['J']['share_common']);
        $this->assertSame(-50.0, $porParticipante['J']['balance']);
    }

    #[Test]
    public function item_de_fatura_entra_no_mes_do_vencimento(): void
    {
        $fatura = CreditCardBill::query()->create([
            'title_description_owner' => 'Fatura de teste',
            'owner_bill' => 'D',
            'amount' => '0.00',
            'due_date' => '2026-02-10',
        ]);

        // Compra em janeiro, fatura vence em fevereiro: o saldo é de fevereiro.
        $fatura->transactions()->create($this->atributos([
            'who_paid' => 'D',
            'amount' => '80.00',
            'transaction_date' => '2026-01-05',
        ]));

        $balances = collect(app(MonthlyBalanceCalculator::class)->calculateFinalBalances());

        $this->assertSame(['2026-02'], $balances->pluck('month_year')->unique()->values()->all());
        $this->assertSame(40.0, $balances->firstWhere('participant', 'D')['balance']);
        $this->assertSame(-40.0, $balances->firstWhere('participant', 'J')['balance']);
    }

    #[Test]
    public function pagamento_de_fatura_nao_entra_no_rateio(): void
    {
        $this->lancamento(['who_paid' => 'D', 'amount' => '200.00']);
        $this->lancamento(['who_paid' => 'J', 'amount' => '999.00', 'type' => 'pgto_de_fatura']);

        $balances = collect(app(MonthlyBalanceCalculator::class)->calculateFinalBalances());

        $this->assertSame(100.0, $balances->firstWhere('participant', 'D')['balance']);
        $this->assertSame(-100.0, $balances->firstWhere('participant', 'J')['balance']);
    }

    #[Test]
    public function despesa_individual_paga_pelo_outro_gera_divida_cruzada(): void
    {
        // Nenhuma despesa comum: só a individual de D paga por J.
        $this->lancamento([
            'who_paid' => 'J',
            'amount' => '60.00',
            'common_expense' => false,
            'individual_expense' => true,
            'responsible_for_expense' => 'D',
        ]);

        $balances = collect(app(MonthlyBalanceCalculator::class)->calculateFinalBalances());

        $this->assertSame(60.0, $balances->firstWhere('participant', 'J')['balance']);
        $this->assertSame(-60.0, $balances->firstWhere('participant', 'D')['balance']);
    }

    #[Test]
    public function o_relatorio_soma_o_adiantamento_ao_total_pago_para_a_linha_exibida_fechar(): void
    {
        // Comportamento herdado do cálculo antigo: a página exibe
        // "pagou X, deveria pagar Y, saldo Z" e essa conta precisa fechar,
        // então o que J adiantou de despesa individual de D entra no "pagou".
        $this->lancamento(['who_paid' => 'J', 'amount' => '300.00']);
        $this->lancamento([
            'who_paid' => 'J',
            'amount' => '90.00',
            'common_expense' => false,
            'individual_expense' => true,
            'responsible_for_expense' => 'D',
        ]);

        $balances = collect(app(MonthlyBalanceCalculator::class)->calculateFinalBalances());
        $j = $balances->firstWhere('participant', 'J');

        $this->assertSame(390.0, $j['total_paid_common']); // 300 comuns + 90 adiantados
        $this->assertSame(150.0, $j['share_common']);
        $this->assertSame(240.0, $j['balance']); // e a linha fecha: 390 - 150 = 240

        // Quem foi adiantado não tem o valor somado ao próprio "total pago".
        $d = $balances->firstWhere('participant', 'D');
        $this->assertSame(0.0, $d['total_paid_common']);
        $this->assertSame(-240.0, $d['balance']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function lancamento(array $atributos): Transaction
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
            'transaction_date' => '2026-01-15',
            'description' => 'Lançamento de teste',
            'amount' => '100.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
        ], $atributos);
    }
}
