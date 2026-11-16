<?php

namespace Tests\Feature;

use App\Filament\Resources\CreditCardBills\Pages\ListCreditCardBills;
use App\Models\CreditCardBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Filtros da listagem de faturas. Diferidos, daí o applyTableFilters.
 * Livewire::test() não passa pelo middleware do painel: não cobre autorização.
 */
final class FiltrosDeFaturasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function filtra_pelo_mes_e_ano_do_vencimento(): void
    {
        $alvo = $this->fatura(['due_date' => '2026-07-15']);
        $outroMes = $this->fatura(['due_date' => '2026-08-15']);
        $outroAno = $this->fatura(['due_date' => '2025-07-15']);

        Livewire::test(ListCreditCardBills::class)
            ->filterTable('vencimento', ['mes' => 7, 'ano' => 2026])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outroMes, $outroAno]);
    }

    #[Test]
    public function filtra_so_pelo_ano(): void
    {
        $julho = $this->fatura(['due_date' => '2026-07-15']);
        $agosto = $this->fatura(['due_date' => '2026-08-15']);
        $anoPassado = $this->fatura(['due_date' => '2025-07-15']);

        Livewire::test(ListCreditCardBills::class)
            ->filterTable('vencimento', ['ano' => 2026])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$julho, $agosto])
            ->assertCanNotSeeTableRecords([$anoPassado]);
    }

    #[Test]
    public function filtra_pelo_dono_da_fatura(): void
    {
        $doD = $this->fatura(['owner_bill' => 'D']);
        $doJ = $this->fatura(['owner_bill' => 'J']);

        Livewire::test(ListCreditCardBills::class)
            ->filterTable('owner_bill', 'J')
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$doJ])
            ->assertCanNotSeeTableRecords([$doD]);
    }

    #[Test]
    public function os_filtros_se_somam(): void
    {
        $alvo = $this->fatura(['owner_bill' => 'D', 'due_date' => '2026-07-15']);
        $outroDono = $this->fatura(['owner_bill' => 'J', 'due_date' => '2026-07-15']);
        $outroMes = $this->fatura(['owner_bill' => 'D', 'due_date' => '2026-08-15']);

        Livewire::test(ListCreditCardBills::class)
            ->filterTable('vencimento', ['mes' => 7, 'ano' => 2026])
            ->filterTable('owner_bill', 'D')
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outroDono, $outroMes]);
    }

    #[Test]
    public function o_seletor_de_ano_lista_so_os_anos_com_fatura(): void
    {
        $this->fatura(['due_date' => '2025-03-10']);
        $this->fatura(['due_date' => '2026-07-15']);
        $this->fatura(['due_date' => '2026-08-15']);

        $select = Livewire::test(ListCreditCardBills::class)
            ->assertOk()
            ->instance()
            ->getTable()
            ->getFilter('vencimento')
            ->getSchema()
            ->getComponent('ano');

        $this->assertSame([2026 => 2026, 2025 => 2025], $select->getOptions());
    }

    /** @param array<string, mixed> $atributos */
    private function fatura(array $atributos = []): CreditCardBill
    {
        return CreditCardBill::factory()->create(array_merge([
            'title_description_owner' => 'Fatura do cartão',
            'due_date' => '2026-07-15',
        ], $atributos));
    }
}
