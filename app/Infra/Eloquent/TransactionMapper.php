<?php

declare(strict_types=1);

namespace App\Infra\Eloquent;

use App\Domain\Finance\Entities\Transaction as TransactionDominio;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\Parcela;
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
            deFatura: $model->creditCardBill !== null,
            parcela: $this->parcela($model),
        );
    }

    /**
     * Mapeia o lançamento de domínio para os atributos do Eloquent — o
     * caminho de escrita, usado pela importação de CSV.
     *
     * @return array<string, mixed>
     */
    public function paraAtributos(TransactionDominio $lancamento): array
    {
        $is_payment = $lancamento->amount()->ehNegativo() && $lancamento->description() === 'Pagamento recebido';
        return [
            'transaction_date' => $lancamento->date()->format('Y-m-d'),
            'description' => $lancamento->description(),
            'amount' => $lancamento->amount()->paraDecimal(), // "-2825.51" para coluna DECIMAL
            'common_expense' => $lancamento->ehDespesaComum(),
            'individual_expense' => $lancamento->ehDespesaIndividual(),
            'who_paid' => $lancamento->whoPaid()->value,
            'installment_current' => $lancamento->parcela()?->atual(),
            'installment_total' => $lancamento->parcela()?->total(),
            'type' => $is_payment ? TransactionType::BillPayment : $lancamento->type(),
        ];
    }

    /**
     * A fonte da verdade da parcela são as colunas, não a descrição: linha
     * antiga com o sufixo no texto e as colunas vazias é caso do comando
     * `transactions:extrair-parcelas`, não de reparse a cada leitura.
     */
    private function parcela(TransactionEloquent $model): ?Parcela
    {
        if ($model->installment_current === null || $model->installment_total === null) {
            return null;
        }

        return Parcela::de($model->installment_current, $model->installment_total);
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
