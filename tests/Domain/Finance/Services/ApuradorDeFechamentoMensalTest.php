<?php

namespace Tests\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\Services\ApuradorDeFechamentoMensal;
use App\Domain\Finance\ValueObjects\FechamentoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApuradorDeFechamentoMensalTest extends TestCase
{
    private ApuradorDeFechamentoMensal $apurador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apurador = new ApuradorDeFechamentoMensal();
    }

    #[Test]
    public function apura_o_mes_completo_com_casa_cartoes_e_divida_cruzada(): void
    {
        $fechamento = $this->apurar([
            // Casa: 300,00 pagos por J.
            $this->casa(30000, whoPaid: Participant::J, label: 'Aluguel'),
            // Cartão de D: 100,00 comum + 40,00 individual dele.
            $this->cartao(10000, whoPaid: Participant::D, scope: ExpenseScope::Comum),
            $this->cartao(4000, whoPaid: Participant::D, scope: ExpenseScope::Individual, responsible: Participant::D),
            // Cartão de J: 50,00 individual que na verdade é de D (dívida cruzada).
            $this->cartao(5000, whoPaid: Participant::J, scope: ExpenseScope::Individual, responsible: Participant::D),
        ]);

        // Comum do mês: 300 (casa) + 100 (cartão D) = 400 -> cota de 200 cada.
        $this->assertTrue($fechamento->totalComum()->equals(Money::emCentavos(40000)));
        $this->assertTrue($fechamento->totalCasa()->equals(Money::emCentavos(30000)));

        $d = $fechamento->de(Participant::D);
        $this->assertTrue($d->cartaoTotal->equals(Money::emCentavos(14000)));
        $this->assertTrue($d->cartaoComum->equals(Money::emCentavos(10000)));
        $this->assertTrue($d->cartaoIndividual->equals(Money::emCentavos(4000)));
        $this->assertTrue($d->pagoComum->equals(Money::emCentavos(10000)));
        $this->assertTrue($d->cotaComum->equals(Money::emCentavos(20000)));
        $this->assertTrue($d->ajusteDividaCruzada->equals(Money::emCentavos(-5000)));
        // Saldo: pagou 100 - cota 200 - 50 adiantados por J = -150.
        $this->assertTrue($d->saldo->equals(Money::emCentavos(-15000)));
        // Custo de vida: cota 200 + individuais dele (40 + 50) = 290.
        $this->assertTrue($d->custoDeVida->equals(Money::emCentavos(29000)));

        $j = $fechamento->de(Participant::J);
        $this->assertTrue($j->cartaoTotal->equals(Money::emCentavos(5000)));
        $this->assertTrue($j->cartaoComum->ehZero());
        $this->assertTrue($j->cartaoIndividual->equals(Money::emCentavos(5000)));
        $this->assertTrue($j->pagoComum->equals(Money::emCentavos(30000)));
        $this->assertTrue($j->ajusteDividaCruzada->equals(Money::emCentavos(5000)));
        // Saldo: pagou 300 - cota 200 + 50 adiantados = +150.
        $this->assertTrue($j->saldo->equals(Money::emCentavos(15000)));
        // Custo de vida: cota 200 + nenhuma individual própria.
        $this->assertTrue($j->custoDeVida->equals(Money::emCentavos(20000)));

        $acertos = $fechamento->acertos();
        $this->assertCount(1, $acertos);
        $this->assertSame(Participant::J, $acertos[0]->recebe);
        $this->assertSame(Participant::D, $acertos[0]->paga);
        $this->assertTrue($acertos[0]->valor->equals(Money::emCentavos(15000)));
    }

    #[Test]
    public function casa_mais_comum_dos_cartoes_fecha_com_o_total_comum(): void
    {
        $fechamento = $this->apurar([
            $this->casa(30000, whoPaid: Participant::J, label: 'Aluguel'),
            // Avulsa comum de descrição qualquer: na regra antiga escapava do
            // total da casa e o total comum não fechava.
            $this->casa(7777, whoPaid: Participant::D, label: 'Outros'),
            $this->cartao(10000, whoPaid: Participant::D, scope: ExpenseScope::Comum),
            $this->cartao(2500, whoPaid: Participant::J, scope: ExpenseScope::Comum),
            $this->cartao(4000, whoPaid: Participant::J, scope: ExpenseScope::Individual, responsible: Participant::J),
        ]);

        $soma = $fechamento->totalCasa()
            ->somar($fechamento->de(Participant::D)->cartaoComum)
            ->somar($fechamento->de(Participant::J)->cartaoComum);

        $this->assertTrue($soma->equals($fechamento->totalComum()));
        $this->assertTrue($fechamento->totalCasa()->equals(Money::emCentavos(37777)));
    }

    #[Test]
    public function agrupa_despesas_da_casa_de_mesma_descricao(): void
    {
        $fechamento = $this->apurar([
            $this->casa(30000, whoPaid: Participant::J, label: 'Aluguel'),
            $this->casa(18000, whoPaid: Participant::D, label: 'LIGHT'),
            $this->casa(2000, whoPaid: Participant::D, label: 'LIGHT'),
        ]);

        $linhas = $fechamento->despesasDaCasa();

        $this->assertCount(2, $linhas);
        $this->assertSame('Aluguel', $linhas[0]->label);
        $this->assertTrue($linhas[0]->valor->equals(Money::emCentavos(30000)));
        $this->assertSame('LIGHT', $linhas[1]->label);
        $this->assertTrue($linhas[1]->valor->equals(Money::emCentavos(20000)));
    }

    #[Test]
    public function mes_sem_lancamento_fecha_zerado(): void
    {
        $fechamento = $this->apurar([]);

        $this->assertTrue($fechamento->totalComum()->ehZero());
        $this->assertTrue($fechamento->totalCasa()->ehZero());
        $this->assertSame([], $fechamento->despesasDaCasa());
        $this->assertSame([], $fechamento->acertos());

        foreach ($fechamento->participantes() as $participante) {
            $this->assertTrue($participante->cartaoTotal->ehZero());
            $this->assertTrue($participante->saldo->ehZero());
            $this->assertTrue($participante->custoDeVida->ehZero());
        }
    }

    #[Test]
    public function item_de_fatura_nao_entra_na_casa_mesmo_sendo_comum(): void
    {
        $fechamento = $this->apurar([
            $this->cartao(10000, whoPaid: Participant::D, scope: ExpenseScope::Comum),
        ]);

        $this->assertTrue($fechamento->totalCasa()->ehZero());
        $this->assertSame([], $fechamento->despesasDaCasa());
        $this->assertTrue($fechamento->totalComum()->equals(Money::emCentavos(10000)));
    }

    #[Test]
    public function pagamento_de_fatura_e_lancamento_de_outro_mes_ficam_de_fora(): void
    {
        $fechamento = $this->apurar([
            $this->casa(30000, whoPaid: Participant::J, label: 'Aluguel'),
            $this->cartao(999999, whoPaid: Participant::D, scope: ExpenseScope::Comum, type: TransactionType::BillPayment),
            $this->casa(50000, whoPaid: Participant::D, label: 'Condomínio', data: '2026-02-10'),
        ]);

        $this->assertTrue($fechamento->totalComum()->equals(Money::emCentavos(30000)));
        $this->assertTrue($fechamento->totalCasa()->equals(Money::emCentavos(30000)));
        $this->assertCount(1, $fechamento->despesasDaCasa());
        $this->assertTrue($fechamento->de(Participant::D)->cartaoTotal->ehZero());
    }

    /**
     * @param  list<Transaction>  $lancamentos
     */
    private function apurar(array $lancamentos): FechamentoMensal
    {
        return $this->apurador->apurar(
            ReferenceMonth::fromString('2026-01'),
            $lancamentos,
            Participant::cases(),
        );
    }

    private function casa(
        int $centavos,
        Participant $whoPaid,
        string $label,
        string $data = '2026-01-15',
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable($data),
            description: $label,
            amount: Money::emCentavos($centavos),
            whoPaid: $whoPaid,
            scope: ExpenseScope::Comum,
            deFatura: false,
        );
    }

    private function cartao(
        int $centavos,
        Participant $whoPaid,
        ExpenseScope $scope,
        ?Participant $responsible = null,
        TransactionType $type = TransactionType::FixedExpense,
        string $data = '2026-01-15',
    ): Transaction {
        return new Transaction(
            date: new DateTimeImmutable($data),
            description: 'Item de fatura',
            amount: Money::emCentavos($centavos),
            whoPaid: $whoPaid,
            scope: $scope,
            responsible: $responsible,
            type: $type,
            deFatura: true,
        );
    }
}
