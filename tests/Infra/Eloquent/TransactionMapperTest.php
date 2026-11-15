<?php

namespace Tests\Infra\Eloquent;

use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Infra\Eloquent\TransactionMapper;
use App\Models\CreditCardBill;
use App\Models\Transaction as TransactionEloquent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Testa o mapeamento sem tocar o banco: os models são montados em memória
 * com os atributos brutos, como viriam de um SELECT.
 */
final class TransactionMapperTest extends TestCase
{
    private TransactionMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new TransactionMapper();
    }

    #[Test]
    public function lancamento_avulso_tem_competencia_da_propria_data(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model([
            'transaction_date' => '2026-03-15',
        ]));

        $this->assertTrue($lancamento->referenceMonth()->equals(ReferenceMonth::fromString('2026-03')));
    }

    #[Test]
    public function item_de_fatura_tem_competencia_do_vencimento_da_fatura(): void
    {
        $model = $this->model([
            'credit_card_bill_id' => 1,
            'transaction_date' => '2026-03-15',
        ]);
        $model->setRelation('creditCardBill', $this->fatura('2026-04-10'));

        $lancamento = $this->mapper->paraDominio($model);

        $this->assertTrue($lancamento->referenceMonth()->equals(ReferenceMonth::fromString('2026-04')));
    }

    #[Test]
    public function converte_o_valor_decimal_para_centavos_sem_perder_precisao(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model(['amount' => '2825.51']));

        $this->assertTrue($lancamento->amount()->equals(Money::emCentavos(282551)));
    }

    #[Test]
    public function converte_credito_negativo(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model(['amount' => '-150.00']));

        $this->assertTrue($lancamento->amount()->equals(Money::emCentavos(-15000)));
        $this->assertTrue($lancamento->ehCredito());
    }

    #[Test]
    public function mapeia_escopo_a_partir_do_flag_de_despesa_comum(): void
    {
        $comum = $this->mapper->paraDominio($this->model(['common_expense' => true]));
        $individual = $this->mapper->paraDominio($this->model(['common_expense' => false]));

        $this->assertSame(ExpenseScope::Comum, $comum->tipo());
        $this->assertSame(ExpenseScope::Individual, $individual->tipo());
    }

    #[Test]
    public function mapeia_participantes_e_tipo(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model([
            'who_paid' => 'J',
            'responsible_for_expense' => 'D',
            'type' => 'pgto_de_fatura',
        ]));

        $this->assertSame(Participant::J, $lancamento->whoPaid());
        $this->assertSame(Participant::D, $lancamento->responsible());
        $this->assertSame(TransactionType::BillPayment, $lancamento->type());
        $this->assertFalse($lancamento->contaParaBalanco());
    }

    #[Test]
    public function responsavel_nulo_permanece_nulo(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model(['responsible_for_expense' => null]));

        $this->assertNull($lancamento->responsible());
    }

    #[Test]
    public function marca_lancamento_vindo_de_fatura(): void
    {
        $model = $this->model(['credit_card_bill_id' => 1]);
        $model->setRelation('creditCardBill', $this->fatura('2026-04-10'));

        $this->assertTrue($this->mapper->paraDominio($model)->ehDeFatura());
    }

    #[Test]
    public function marca_lancamento_avulso_como_fora_de_fatura(): void
    {
        $this->assertFalse($this->mapper->paraDominio($this->model())->ehDeFatura());
    }

    #[Test]
    public function mapeia_colunas_de_parcela_para_o_value_object(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model([
            'description' => 'Garmin Proparts Ecomm - Parcela 3/6',
            'installment_current' => 3,
            'installment_total' => 6,
        ]));

        $this->assertTrue($lancamento->ehParcelada());
        $this->assertSame('3/6', $lancamento->parcela()->rotulo());
        $this->assertSame('Garmin Proparts Ecomm', $lancamento->descricaoDaSerie());
    }

    #[Test]
    public function colunas_de_parcela_nulas_produzem_parcela_nula(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model());

        $this->assertNull($lancamento->parcela());
    }

    #[Test]
    public function apenas_uma_coluna_de_parcela_preenchida_produz_parcela_nula(): void
    {
        // Sem o total não dá para dizer se a série acabou; meia parcela não é
        // parcela. A descrição ainda traz o sufixo, mas o mapper não reparseia.
        $lancamento = $this->mapper->paraDominio($this->model([
            'description' => 'Garmin Proparts Ecomm - Parcela 3/6',
            'installment_current' => 3,
            'installment_total' => null,
        ]));

        $this->assertNull($lancamento->parcela());
    }

    #[Test]
    public function para_atributos_grava_as_colunas_de_parcela(): void
    {
        $lancamento = $this->mapper->paraDominio($this->model([
            'description' => 'Decolar Com - Parcela 2/12',
            'amount' => '908.10',
            'who_paid' => 'J',
            'installment_current' => 2,
            'installment_total' => 12,
        ]));

        $atributos = $this->mapper->paraAtributos($lancamento);

        $this->assertSame('Decolar Com - Parcela 2/12', $atributos['description']);
        $this->assertSame('908.10', $atributos['amount']);
        $this->assertSame('J', $atributos['who_paid']);
        $this->assertSame(2, $atributos['installment_current']);
        $this->assertSame(12, $atributos['installment_total']);
    }

    #[Test]
    public function para_atributos_de_lancamento_sem_parcela_deixa_as_colunas_nulas(): void
    {
        $atributos = $this->mapper->paraAtributos(
            $this->mapper->paraDominio($this->model()),
        );

        $this->assertNull($atributos['installment_current']);
        $this->assertNull($atributos['installment_total']);
    }

    /**
     * Monta o model como ele chega do repositório: atributos brutos e a
     * relação já carregada (o repositório sempre faz eager load, então o
     * mapper nunca vê uma relação pendente).
     *
     * @param  array<string, mixed>  $atributos
     */
    private function model(array $atributos = []): TransactionEloquent
    {
        $model = new TransactionEloquent();
        $model->setRelation('creditCardBill', null);

        $model->setRawAttributes(array_merge([
            'id' => 1,
            'credit_card_bill_id' => null,
            'transaction_date' => '2026-01-15',
            'description' => 'Lançamento de teste',
            'amount' => '100.00',
            'common_expense' => 1,
            'individual_expense' => 0,
            'responsible_for_expense' => null,
            'who_paid' => 'D',
            'type' => 'fixed_expense',
            'installment_current' => null,
            'installment_total' => null,
        ], $atributos), sync: true);

        return $model;
    }

    private function fatura(string $vencimento): CreditCardBill
    {
        $fatura = new CreditCardBill();

        $fatura->setRawAttributes([
            'id' => 1,
            'due_date' => $vencimento,
        ], sync: true);

        return $fatura;
    }
}
