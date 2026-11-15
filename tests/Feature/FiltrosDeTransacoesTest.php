<?php

namespace Tests\Feature;

use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Os filtros da listagem de transações. Renderizar de verdade é o que pega
 * coluna inexistente e violação de preventLazyLoading(); os filtros são
 * diferidos (o painel só aplica no botão), daí o applyTableFilters.
 *
 * Atenção ao alcance: Livewire::test() monta a página sem o middleware do
 * painel, então isto não cobre autorização.
 */
final class FiltrosDeTransacoesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    #[Test]
    public function filtra_por_parte_da_descricao(): void
    {
        $mercado = $this->lancamento(['description' => 'Supermercado Dia']);
        $farmacia = $this->lancamento(['description' => 'Farmácia']);

        Livewire::test(ListTransactions::class)
            ->filterTable('description', ['description' => 'mercado'])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$mercado])
            ->assertCanNotSeeTableRecords([$farmacia]);
    }

    #[Test]
    public function filtra_pelo_intervalo_de_datas(): void
    {
        $antes = $this->lancamento(['transaction_date' => '2026-06-30']);
        $dentro = $this->lancamento(['transaction_date' => '2026-07-10']);
        $depois = $this->lancamento(['transaction_date' => '2026-08-01']);

        Livewire::test(ListTransactions::class)
            ->filterTable('transaction_date', ['de' => '2026-07-01', 'ate' => '2026-07-31'])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$dentro])
            ->assertCanNotSeeTableRecords([$antes, $depois]);
    }

    #[Test]
    public function o_limite_do_intervalo_entra_no_resultado(): void
    {
        $primeiroDia = $this->lancamento(['transaction_date' => '2026-07-01']);
        $ultimoDia = $this->lancamento(['transaction_date' => '2026-07-31']);

        Livewire::test(ListTransactions::class)
            ->filterTable('transaction_date', ['de' => '2026-07-01', 'ate' => '2026-07-31'])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$primeiroDia, $ultimoDia]);
    }

    #[Test]
    public function so_a_data_inicial_ja_filtra(): void
    {
        $antes = $this->lancamento(['transaction_date' => '2026-06-30']);
        $depois = $this->lancamento(['transaction_date' => '2026-07-10']);

        Livewire::test(ListTransactions::class)
            ->filterTable('transaction_date', ['de' => '2026-07-01', 'ate' => null])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$depois])
            ->assertCanNotSeeTableRecords([$antes]);
    }

    #[Test]
    public function filtra_pela_fatura_relacionada(): void
    {
        $faturaD = $this->fatura(['owner_bill' => 'D']);
        $faturaJ = $this->fatura(['owner_bill' => 'J']);

        $doD = $faturaD->transactions()->create($this->atributos());
        $doJ = $faturaJ->transactions()->create($this->atributos());
        $avulso = $this->lancamento();

        Livewire::test(ListTransactions::class)
            ->filterTable('credit_card_bill_id', [$faturaD->getKey()])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$doD])
            ->assertCanNotSeeTableRecords([$doJ, $avulso]);
    }

    #[Test]
    public function o_filtro_de_fatura_aceita_mais_de_uma(): void
    {
        $faturaD = $this->fatura(['owner_bill' => 'D']);
        $faturaJ = $this->fatura(['owner_bill' => 'J']);

        $doD = $faturaD->transactions()->create($this->atributos());
        $doJ = $faturaJ->transactions()->create($this->atributos());
        $avulso = $this->lancamento();

        Livewire::test(ListTransactions::class)
            ->filterTable('credit_card_bill_id', [$faturaD->getKey(), $faturaJ->getKey()])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$doD, $doJ])
            ->assertCanNotSeeTableRecords([$avulso]);
    }

    #[Test]
    public function os_filtros_se_somam(): void
    {
        $fatura = $this->fatura();

        $alvo = $fatura->transactions()->create($this->atributos([
            'description' => 'Supermercado Dia',
            'transaction_date' => '2026-07-10',
        ]));
        $outraDescricao = $fatura->transactions()->create($this->atributos([
            'description' => 'Farmácia',
            'transaction_date' => '2026-07-10',
        ]));
        $foraDoIntervalo = $fatura->transactions()->create($this->atributos([
            'description' => 'Supermercado Dia',
            'transaction_date' => '2026-08-10',
        ]));

        Livewire::test(ListTransactions::class)
            ->filterTable('description', ['description' => 'supermercado'])
            ->filterTable('transaction_date', ['de' => '2026-07-01', 'ate' => '2026-07-31'])
            ->filterTable('credit_card_bill_id', [$fatura->getKey()])
            ->call('applyTableFilters')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outraDescricao, $foraDoIntervalo]);
    }

    #[Test]
    public function o_seletor_de_fatura_lista_as_faturas_pelo_rotulo(): void
    {
        $this->fatura(['title_description_owner' => 'Nubank', 'owner_bill' => 'D']);
        // owner_bill nulo existe em fatura antiga e não pode quebrar o rótulo.
        $this->fatura(['title_description_owner' => 'Itaú', 'owner_bill' => null]);

        $pagina = Livewire::test(ListTransactions::class)
            ->assertOk()
            ->instance();

        $select = $pagina->getTable()
            ->getFilter('credit_card_bill_id')
            ->getSchema()
            ->getComponent('values');

        $rotulos = array_values($select->getOptions());

        $this->assertContains('Nubank (D) — venc. 07/2026', $rotulos);
        $this->assertContains('Itaú — venc. 07/2026', $rotulos);
    }

    #[Test]
    public function sem_filtro_a_listagem_mostra_tudo(): void
    {
        $avulso = $this->lancamento();
        $deFatura = $this->fatura()->transactions()->create($this->atributos());

        Livewire::test(ListTransactions::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$avulso, $deFatura]);
    }

    /** @param array<string, mixed> $atributos */
    private function fatura(array $atributos = []): CreditCardBill
    {
        return CreditCardBill::factory()->create(array_merge([
            'title_description_owner' => 'Fatura do cartão',
            'due_date' => '2026-07-15',
        ], $atributos));
    }

    /** @param array<string, mixed> $atributos */
    private function lancamento(array $atributos = []): Transaction
    {
        return Transaction::create($this->atributos($atributos));
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function atributos(array $atributos = []): array
    {
        return array_merge([
            'transaction_date' => '2026-07-05',
            'description' => 'Item de fatura',
            'amount' => '100.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
        ], $atributos);
    }
}
