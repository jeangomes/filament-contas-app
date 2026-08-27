<?php

declare(strict_types=1);

namespace App\Domain\Finance\Entities;

use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\ValueObjects\Money;
use DateTimeImmutable;

readonly class Transaction
{
    public function __construct(
        private DateTimeImmutable $date,
        private string            $description,
        private Money             $amount,
        private string            $whoPaid,
        private ExpenseScope      $scope,
        private string|null       $responsible, // Quem deveria pagar
        private bool|null         $isShared,       // É despesa comum?

    ) {}

    // Getters
    public function getAmount(): Money { return $this->amount; }
    public function getResponsibleId(): string { return $this->responsible; }
    public function isShared(): bool { return $this->isShared; }

    // Regra de Negócio na Entidade: É uma dívida individual cruzada?
    // Ex: "D" pagou, mas a conta era do "J" e não é compartilhada.
    public function isCrossDebt(): bool
    {
        return !$this->isShared && ($this->whoPaid !== $this->responsible);
    }

    ///
    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function whoPaid(): string
    {
        return $this->whoPaid;
    }

    public function tipo(): ExpenseScope
    {
        return $this->scope;
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
}
