<?php

declare(strict_types=1);

namespace App\Domain\Finance\Entities;

use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\Rubrica;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\Parcela;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use DateTimeImmutable;

readonly class Transaction
{
    public function __construct(
        private DateTimeImmutable $date,
        private string            $description,
        private Money             $amount,
        private Participant       $whoPaid,
        private ExpenseScope      $scope,
        private ?Participant      $responsible = null, // Quem deveria pagar
        private TransactionType   $type = TransactionType::FixedExpense,
        private bool              $deFatura = false,   // Veio de fatura de cartão?
        private ?Parcela          $parcela = null,     // "3/6" numa compra parcelada
    ) {}

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function referenceMonth(): ReferenceMonth
    {
        return ReferenceMonth::fromDate($this->date);
    }

    public function description(): string
    {
        return $this->description;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function parcela(): ?Parcela
    {
        return $this->parcela;
    }

    public function ehParcelada(): bool
    {
        return $this->parcela !== null;
    }

    /**
     * A descrição sem o sufixo "- Parcela N/M": é ela que identifica a compra
     * ao longo dos meses, já que cada parcela chega como um lançamento com
     * descrição diferente.
     */
    public function descricaoDaSerie(): string
    {
        return Parcela::descricaoDaSerie($this->description);
    }

    public function whoPaid(): Participant
    {
        return $this->whoPaid;
    }

    public function responsible(): ?Participant
    {
        return $this->responsible;
    }

    /**
     * Quem de fato arca com a despesa. Quando ninguém foi apontado como
     * responsável, quem pagou é quem arca — é o que sustenta o custo de vida
     * dos lançamentos sem 'responsible' preenchido.
     */
    public function responsavelEfetivo(): Participant
    {
        return $this->responsible ?? $this->whoPaid;
    }

    public function tipo(): ExpenseScope
    {
        return $this->scope;
    }

    public function type(): TransactionType
    {
        return $this->type;
    }

    /**
     * Item de fatura de cartão (por oposição a lançamento avulso). A
     * competência já foi resolvida na origem, então essa informação precisa
     * viajar junto: é ela que separa "casa" de "cartão".
     */
    public function ehDeFatura(): bool
    {
        return $this->deFatura;
    }

    /**
     * Créditos (ex.: "Pagamento recebido") vêm com valor negativo.
     */
    public function ehCredito(): bool
    {
        return $this->amount->ehCredito();
    }

    public function ehDespesaComum(): bool
    {
        return $this->scope->ehComum();
    }

    public function ehDespesaIndividual(): bool
    {
        return ! $this->scope->ehComum();
    }

    public function contaParaBalanco(): bool
    {
        return $this->type->contaParaBalanco();
    }

    /**
     * Regra de Negócio na Entidade: É uma dívida individual cruzada?
     * Ex: "D" pagou, mas a conta era do "J" e não é compartilhada.
     */
    public function isCrossDebt(): bool
    {
        return $this->ehDespesaIndividual()
            && $this->responsavelEfetivo() !== $this->whoPaid;
    }

    /**
     * O caminho deste lançamento no fechamento. Não é regra nova: só dá nome
     * à combinação dos predicados acima, na mesma ordem em que as
     * calculadoras os aplicam.
     *
     * A ordem importa: um pagamento de fatura marcado como comum continua
     * fora do rateio.
     */
    public function rubrica(): Rubrica
    {
        return match (true) {
            ! $this->contaParaBalanco() => Rubrica::ForaDoRateio,
            $this->ehDespesaComum() => $this->deFatura ? Rubrica::ComumDeCartao : Rubrica::ComumDaCasa,
            $this->isCrossDebt() => Rubrica::DividaCruzada,
            default => Rubrica::IndividualPropria,
        };
    }
}
