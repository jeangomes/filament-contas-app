<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O backfill das colunas de parcela no histórico que foi importado antes de
 * a extração existir.
 */
final class ExtrairParcelasCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function preenche_as_colunas_a_partir_da_descricao(): void
    {
        $lancamento = $this->lancamento('Garmin Proparts Ecomm - Parcela 3/6');

        $this->artisan('transactions:extrair-parcelas')->assertSuccessful();

        $lancamento->refresh();
        $this->assertSame(3, $lancamento->installment_current);
        $this->assertSame(6, $lancamento->installment_total);
        // A descrição continua íntegra: o dado estruturado anda ao lado dela.
        $this->assertSame('Garmin Proparts Ecomm - Parcela 3/6', $lancamento->description);
    }

    #[Test]
    public function e_idempotente(): void
    {
        $lancamento = $this->lancamento('Decolar Com - Parcela 2/12');

        $this->artisan('transactions:extrair-parcelas')->assertSuccessful();
        $atualizadoEm = $lancamento->refresh()->updated_at;

        $this->artisan('transactions:extrair-parcelas')->assertSuccessful();

        $lancamento->refresh();
        $this->assertSame(2, $lancamento->installment_current);
        $this->assertSame(12, $lancamento->installment_total);
        $this->assertEquals($atualizadoEm, $lancamento->updated_at);
    }

    #[Test]
    public function dry_run_nao_grava(): void
    {
        $lancamento = $this->lancamento('Mlp*Netshoes - Parcela 5/10');

        $this->artisan('transactions:extrair-parcelas', ['--dry-run' => true])->assertSuccessful();

        $lancamento->refresh();
        $this->assertNull($lancamento->installment_current);
        $this->assertNull($lancamento->installment_total);
    }

    #[Test]
    public function ignora_descricao_sem_sufixo_de_parcela(): void
    {
        // Casa com o `like '%Parcela%'` do filtro grosso, mas não com a regex.
        $lancamento = $this->lancamento('Parcelamento de anuidade');

        $this->artisan('transactions:extrair-parcelas')->assertSuccessful();

        $this->assertNull($lancamento->refresh()->installment_total);
    }

    #[Test]
    public function nao_sobrescreve_parcela_ja_preenchida_a_mao(): void
    {
        $lancamento = $this->lancamento('Loja Qualquer - Parcela 2/4');
        $lancamento->update(['installment_current' => 1, 'installment_total' => 4]);

        $this->artisan('transactions:extrair-parcelas')->assertSuccessful();

        $this->assertSame(1, $lancamento->refresh()->installment_current);
    }

    private function lancamento(string $descricao): Transaction
    {
        return Transaction::query()->create([
            'transaction_date' => '2026-08-04',
            'description' => $descricao,
            'amount' => '411.50',
            'common_expense' => false,
            'individual_expense' => true,
            'who_paid' => 'J',
            'type' => 'variable_expense',
        ]);
    }
}
