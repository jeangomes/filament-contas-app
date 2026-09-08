<?php

declare(strict_types=1);

namespace App\Domain\Finance\Entities;

use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
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

    public function whoPaid(): Participant
    {
        return $this->whoPaid;
    }

    public function responsible(): ?Participant
    {
        return $this->responsible;
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
            && $this->responsible !== null
            && $this->whoPaid !== $this->responsible;
    }
}
