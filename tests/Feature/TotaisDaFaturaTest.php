<?php

namespace Tests\Feature;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Os totais agregados da fatura são cache de exibição, recalculado **só na
 * edição** de uma transação: importar e apagar não disparam recálculo, para
 * não pagar um SUM por linha na importação de uma fatura inteira.
 */
final class TotaisDaFaturaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function importar_transacoes_nao_recalcula_os_totais(): void
    {
        // Este é o caminho da importação de CSV: os totais ficam parados até
        // alguém editar algum lançamento da fatura.
        $fatura = $this->fatura();

        $fatura->transactions()->createMany([
            $this->atributos(['amount' => '100.00']),
            $this->atributos(['amount' => '50.00']),
            $this->atributos(['amount' => '30.00', 'common_expense' => false, 'individual_expense' => true]),
        ]);

        $fatura->refresh();
        $this->assertSame('0.00', $fatura->common_amount);
        $this->assertSame('0.00', $fatura->individual_amount);
    }

    #[Test]
    public function editar_qualquer_transacao_acerta_a_fatura_toda(): void
    {
        // É por aqui que os totais de uma fatura recém-importada se acertam:
        // o recálculo é da fatura inteira, não só da linha editada.
        $fatura = $this->fatura();
        $fatura->transactions()->createMany([
            $this->atributos(['amount' => '100.00']),
            $this->atributos(['amount' => '50.00']),
        ]);

        $fatura->transactions()->first()->update(['description' => 'Mercado']);

        $this->assertSame('150.00', $fatura->refresh()->common_amount);
    }

    #[Test]
    public function apagar_transacao_nao_recalcula_os_totais(): void
    {
        $fatura = $this->fatura();
        $lancamento = $fatura->transactions()->create($this->atributos(['amount' => '100.00']));
        $descartada = $fatura->transactions()->create($this->atributos(['amount' => '40.00']));

        // Uma edição antes, para os totais saírem do zero.
        $lancamento->update(['description' => 'Mercado']);
        $this->assertSame('140.00', $fatura->refresh()->common_amount);

        $descartada->delete();

        // O apagado continua contado: só uma edição posterior acerta.
        $this->assertSame('140.00', $fatura->refresh()->common_amount);

        $lancamento->update(['description' => 'Mercado do mês']);
        $this->assertSame('100.00', $fatura->refresh()->common_amount);
    }

    #[Test]
    public function editar_o_valor_atualiza_os_totais(): void
    {
        $fatura = $this->fatura();
        $lancamento = $fatura->transactions()->create($this->atributos(['amount' => '100.00']));

        $lancamento->update(['amount' => '250.00']);

        $this->assertSame('250.00', $fatura->refresh()->common_amount);
    }

    #[Test]
    public function mover_transacao_de_fatura_atualiza_as_duas(): void
    {
        $origem = $this->fatura();
        $destino = $this->fatura();
        $lancamento = $origem->transactions()->create($this->atributos(['amount' => '100.00']));

        $lancamento->creditCardBill()->associate($destino)->save();

        $this->assertSame('0.00', $origem->refresh()->common_amount);
        $this->assertSame('100.00', $destino->refresh()->common_amount);
    }

    #[Test]
    public function pagamento_de_fatura_nao_entra_nos_totais(): void
    {
        $fatura = $this->fatura();
        $fatura->transactions()->createMany([
            $this->atributos(['amount' => '100.00']),
            $this->atributos(['amount' => '900.00', 'type' => 'pgto_de_fatura']),
        ]);

        $this->dispararRecalculo($fatura);

        $this->assertSame('100.00', $fatura->refresh()->common_amount);
    }

    #[Test]
    public function o_total_informado_da_fatura_nao_e_sobrescrito(): void
    {
        // Divergência entre o informado e a soma é informação útil.
        $fatura = $this->fatura(['amount' => '359.00']);
        $fatura->transactions()->create($this->atributos(['amount' => '125.00']));

        $this->dispararRecalculo($fatura);

        $this->assertSame(359.0, $fatura->refresh()->amount);
        $this->assertSame('125.00', $fatura->common_amount);
    }

    #[Test]
    public function lancamento_avulso_nao_quebra_nada(): void
    {
        $lancamento = Transaction::query()->create($this->atributos(['amount' => '80.00']));

        $lancamento->update(['amount' => '90.00']);
        $lancamento->delete();

        $this->assertSame(0, CreditCardBill::query()->count());
    }

    /**
     * O recálculo só acontece no `updated`, então quem quer conferir os totais
     * depois de uma inserção precisa editar alguma transação da fatura — é o
     * que o usuário faz na tela.
     */
    private function dispararRecalculo(CreditCardBill $fatura): void
    {
        $fatura->transactions()->first()->update(['description' => 'Editado']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function fatura(array $atributos = []): CreditCardBill
    {
        return CreditCardBill::query()->create(array_merge([
            'title_description_owner' => 'Fatura de teste',
            'owner_bill' => 'D',
            'amount' => '0.00',
            'due_date' => '2026-07-10',
        ], $atributos));
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
