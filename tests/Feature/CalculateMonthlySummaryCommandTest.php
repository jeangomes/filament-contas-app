<?php

namespace Tests\Feature;

use App\Models\CreditCardBill;
use App\Models\MonthlyParticipantSummary;
use App\Models\MonthlySummary;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class CalculateMonthlySummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fecha_a_competencia_informada(): void
    {
        $this->lancamento(['transaction_date' => '2026-07-15', 'amount' => '300.00', 'who_paid' => 'J']);

        $this->artisan('monthly:calculate', ['competencia' => '2026-07'])
            ->assertSuccessful();

        $resumo = MonthlySummary::query()->sole();
        $this->assertSame('2026-07-01', $resumo->competencia->format('Y-m-d'));
        $this->assertSame('300.00', $resumo->total_common_expenses);
    }

    #[Test]
    public function usa_o_mes_atual_quando_nenhuma_competencia_e_informada(): void
    {
        $this->artisan('monthly:calculate')->assertSuccessful();

        $this->assertSame(
            now()->startOfMonth()->format('Y-m-d'),
            MonthlySummary::query()->sole()->competencia->format('Y-m-d'),
        );
    }

    #[Test]
    #[TestWith(['2026-13'])]
    #[TestWith(['abc'])]
    #[TestWith(['2026-1'])]
    public function recusa_competencia_invalida_sem_stack_trace(string $invalida): void
    {
        $this->artisan('monthly:calculate', ['competencia' => $invalida])
            ->expectsOutputToContain('Mês de competência inválido')
            ->assertFailed();

        $this->assertSame(0, MonthlySummary::query()->count());
    }

    #[Test]
    public function recusa_competencia_e_all_ao_mesmo_tempo(): void
    {
        $this->artisan('monthly:calculate', ['competencia' => '2026-07', '--all' => true])
            ->assertFailed();

        $this->assertSame(0, MonthlySummary::query()->count());
    }

    #[Test]
    public function all_fecha_todas_as_competencias_com_lancamento(): void
    {
        $this->lancamento(['transaction_date' => '2026-06-10']);
        $this->lancamento(['transaction_date' => '2026-07-10']);

        // Item de fatura entra pela competência do vencimento, não da compra.
        $fatura = CreditCardBill::query()->create([
            'title_description_owner' => 'Fatura D',
            'owner_bill' => 'D',
            'amount' => '0.00',
            'due_date' => '2026-09-10',
        ]);
        $fatura->transactions()->create($this->atributos(['transaction_date' => '2026-08-02']));

        $this->artisan('monthly:calculate', ['--all' => true])->assertSuccessful();

        $this->assertSame(
            ['2026-06-01', '2026-07-01', '2026-09-01'],
            MonthlySummary::query()->orderBy('competencia')->pluck('competencia')
                ->map(fn ($data): string => $data->format('Y-m-d'))->all(),
        );
    }

    #[Test]
    public function rodar_all_duas_vezes_nao_duplica(): void
    {
        $this->lancamento(['transaction_date' => '2026-07-10']);

        $this->artisan('monthly:calculate', ['--all' => true])->assertSuccessful();
        $this->artisan('monthly:calculate', ['--all' => true])->assertSuccessful();

        $this->assertSame(1, MonthlySummary::query()->count());
        $this->assertSame(2, MonthlyParticipantSummary::query()->count());
    }

    #[Test]
    public function all_sem_nenhum_lancamento_avisa_e_nao_falha(): void
    {
        $this->artisan('monthly:calculate', ['--all' => true])
            ->expectsOutputToContain('Nenhum lançamento encontrado')
            ->assertSuccessful();

        $this->assertSame(0, MonthlySummary::query()->count());
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
            'transaction_date' => '2026-07-15',
            'description' => 'Aluguel',
            'amount' => '100.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
        ], $atributos);
    }
}
