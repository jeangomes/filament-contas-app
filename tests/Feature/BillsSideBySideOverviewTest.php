<?php

namespace Tests\Feature;

use App\Filament\Pages\BillsSideBySideOverview;
use App\Models\CreditCardBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A página sobrevive à limpeza da Task 09 e consome `common_amount` /
 * `individual_amount`, que passaram a ser string (cast decimal:2) na Task 07.
 * Renderizar de verdade é o que garante que a mudança de tipo não quebrou a
 * formatação.
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
    }

    #[Test]
    public function renderiza_com_as_duas_faturas_e_os_totais_recalculados(): void
    {
        $mes = now()->format('Y-m');

        foreach (['D', 'J'] as $dono) {
            $fatura = CreditCardBill::query()->create([
                'title_description_owner' => "Fatura {$dono}",
                'owner_bill' => $dono,
                'amount' => '0.00',
                'due_date' => $mes . '-10',
            ]);

            // O observer da Task 07 preenche os totais agregados.
            $fatura->transactions()->create([
                'transaction_date' => $mes . '-05',
                'description' => 'Mercado',
                'amount' => '150.00',
                'common_expense' => true,
                'individual_expense' => false,
                'who_paid' => $dono,
                'type' => 'fixed_expense',
            ]);
        }

        Livewire::test(BillsSideBySideOverview::class)
            ->assertOk()
            ->assertSee('150,00'); // número formatado a partir da string do cast
    }
}
