<?php

declare(strict_types=1);

namespace App\Infra\Eloquent;

use App\Domain\Finance\Entities\Transaction as TransactionDominio;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
use App\Infra\Csv\ConversorDeValorBrasileiro;
use App\Models\Transaction as TransactionEloquent;
use DateTimeImmutable;

/**
 * Converte o model Eloquent no lançamento de domínio.
 *
 * Aqui mora a resolução da competência: item de fatura é do mês do
 * vencimento da fatura; lançamento avulso é do mês da própria data.
 */
final class TransactionMapper
{
    public function __construct(
        // O conversor aceita tanto o formato BR ("2.825,51") quanto o formato
        // decimal do banco ("2825.51"), então é reaproveitado aqui em vez de
        // duplicar a conversão para centavos.
        private readonly ConversorDeValorBrasileiro $conversor = new ConversorDeValorBrasileiro(),
    ) {
    }

    public function paraDominio(TransactionEloquent $model): TransactionDominio
    {
        $competencia = $model->creditCardBill?->due_date ?? $model->transaction_date;

        return new TransactionDominio(
            date: DateTimeImmutable::createFromInterface($competencia),
            description: (string) $model->description,
            amount: $this->valor($model),
            whoPaid: Participant::from($model->who_paid),
            scope: $model->common_expense ? ExpenseScope::Comum : ExpenseScope::Individual,
            responsible: $model->responsible_for_expense !== null
                ? Participant::from($model->responsible_for_expense)
                : null,
            type: TransactionType::from($model->type),
        );
    }

    /**
     * Lê o valor bruto do banco em vez do atributo já convertido: o model
     * declara o cast 'amount' => 'float', que perderia precisão a caminho
     * dos centavos.
     */
    private function valor(TransactionEloquent $model): Money
    {
        return $this->conversor->converter((string) $model->getRawOriginal('amount'));
    }
}
