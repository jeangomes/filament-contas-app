<?php

namespace Tests\Infra\Eloquent;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Infra\Eloquent\RepositorioDeLancamentosEloquent;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RepositorioDeLancamentosEloquentTest extends TestCase
{
    use RefreshDatabase;

    private RepositorioDeLancamentosEloquent $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new RepositorioDeLancamentosEloquent();
    }

    #[Test]
    public function a_porta_do_dominio_resolve_para_o_adapter_eloquent(): void
    {
        $this->assertInstanceOf(
            RepositorioDeLancamentosEloquent::class,
            app(RepositorioDeLancamentos::class),
        );
    }

    #[Test]
    public function item_de_fatura_pertence_a_competencia_do_vencimento_e_nao_a_da_compra(): void
    {
        $fatura = $this->fatura('2026-04-10');

        $this->lancamento(['transaction_date' => '2026-03-15'], $fatura);

        $this->assertCount(1, $this->repositorio->paraCompetencia(ReferenceMonth::fromString('2026-04')));
        $this->assertCount(0, $this->repositorio->paraCompetencia(ReferenceMonth::fromString('2026-03')));
    }

    #[Test]
    public function lancamento_avulso_pertence_a_competencia_da_propria_data(): void
    {
        $this->lancamento(['transaction_date' => '2026-03-15']);

        $this->assertCount(1, $this->repositorio->paraCompetencia(ReferenceMonth::fromString('2026-03')));
        $this->assertCount(0, $this->repositorio->paraCompetencia(ReferenceMonth::fromString('2026-04')));
    }

    #[Test]
    public function a_consulta_da_competencia_devolve_os_models_pela_mesma_regra(): void
    {
        $fatura = $this->fatura('2026-07-10');

        $itemDeFatura = $this->lancamento(['transaction_date' => '2026-06-20'], $fatura);
        $avulsoDoMes = $this->lancamento(['transaction_date' => '2026-07-05']);
        $this->lancamento(['transaction_date' => '2026-06-05']); // avulso do mês anterior

        $ids = $this->repositorio
            ->consultaDaCompetencia(ReferenceMonth::fromString('2026-07'))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$itemDeFatura->id, $avulsoDoMes->id], $ids);
    }

    #[Test]
    public function a_consulta_da_competencia_nao_filtra_o_pagamento_de_fatura(): void
    {
        $fatura = $this->fatura('2026-07-10');

        $pagamento = $this->lancamento([
            'transaction_date' => '2026-06-10',
            'description' => 'Pagamento recebido',
            'amount' => '-500.00',
            'type' => 'pgto_de_fatura',
        ], $fatura);

        $encontrados = $this->repositorio
            ->consultaDaCompetencia(ReferenceMonth::fromString('2026-07'))
            ->with('creditCardBill')
            ->get();

        // Quem tira o pagamento do rateio é o domínio; o relatório precisa
        // enxergá-lo para mostrar o que ficou de fora.
        $this->assertCount(1, $encontrados);
        $this->assertTrue($encontrados[0]->is($pagamento));
    }

    #[Test]
    public function converte_o_valor_do_banco_para_centavos_sem_perder_precisao(): void
    {
        $this->lancamento(['transaction_date' => '2026-03-15', 'amount' => '2825.51']);

        $lancamentos = $this->repositorio->paraCompetencia(ReferenceMonth::fromString('2026-03'));

        $this->assertTrue($lancamentos[0]->amount()->equals(Money::emCentavos(282551)));
        $this->assertSame(Participant::D, $lancamentos[0]->whoPaid());
    }

    #[Test]
    public function lista_as_competencias_distintas_em_ordem(): void
    {
        $fatura = $this->fatura('2026-04-10');

        $this->lancamento(['transaction_date' => '2026-03-15'], $fatura);
        $this->lancamento(['transaction_date' => '2026-01-20']);
        $this->lancamento(['transaction_date' => '2026-01-25']);

        $chaves = array_map(
            fn (ReferenceMonth $mes): string => $mes->chave(),
            $this->repositorio->competenciasDisponiveis(),
        );

        $this->assertSame(['2026-01', '2026-04'], $chaves);
    }

    #[Test]
    public function traz_so_os_parcelados_com_a_competencia_da_fatura(): void
    {
        $fatura = $this->fatura('2026-08-11');

        $this->lancamento([
            'transaction_date' => '2026-07-04',
            'description' => 'Decolar Com - Parcela 2/12',
            'installment_current' => 2,
            'installment_total' => 12,
        ], $fatura);

        $this->lancamento(['description' => 'Deezerbr Deezer'], $fatura);

        $parcelados = $this->repositorio->parcelados();

        $this->assertCount(1, $parcelados);
        $this->assertSame('2/12', $parcelados[0]->parcela()->rotulo());
        // Competência é a do vencimento da fatura, não a da compra.
        $this->assertSame('2026-08', $parcelados[0]->referenceMonth()->chave());
    }

    private function fatura(string $vencimento): CreditCardBill
    {
        return CreditCardBill::query()->create([
            'title_description_owner' => 'Fatura de teste',
            'owner_bill' => 'D',
            'amount' => '0.00',
            'due_date' => $vencimento,
        ]);
    }

    /**
     * Cria o lançamento como a produção faz: item de fatura nasce pela
     * relação (ver CreateCreditCardBill::afterCreate), avulso nasce solto.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function lancamento(array $atributos, ?CreditCardBill $fatura = null): Transaction
    {
        $atributos = array_merge([
            'transaction_date' => '2026-01-15',
            'description' => 'Lançamento de teste',
            'amount' => '100.00',
            'common_expense' => true,
            'individual_expense' => false,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
        ], $atributos);

        return $fatura !== null
            ? $fatura->transactions()->create($atributos)
            : Transaction::query()->create($atributos);
    }
}
