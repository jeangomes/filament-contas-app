<?php

declare(strict_types=1);

namespace App\Infra\Eloquent;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Models\Transaction as TransactionEloquent;
use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Builder;

final class RepositorioDeLancamentosEloquent implements RepositorioDeLancamentos
{
    public function __construct(
        private readonly TransactionMapper $mapper = new TransactionMapper(),
    ) {
    }

    /**
     * @return list<Transaction>
     */
    public function paraCompetencia(ReferenceMonth $mes): array
    {
        return TransactionEloquent::query()
            ->with('creditCardBill')
            ->where(function (Builder $query) use ($mes) {
                $query
                    ->where(function (Builder $avulso) use ($mes) {
                        $avulso->whereNull('credit_card_bill_id')
                            ->whereYear('transaction_date', $mes->ano())
                            ->whereMonth('transaction_date', $mes->numeroDoMes());
                    })
                    ->orWhereHas('creditCardBill', function (Builder $fatura) use ($mes) {
                        $fatura->whereYear('due_date', $mes->ano())
                            ->whereMonth('due_date', $mes->numeroDoMes());
                    });
            })
            ->get()
            ->map(fn (TransactionEloquent $model): Transaction => $this->mapper->paraDominio($model))
            ->all();
    }

    /**
     * @return list<Transaction>
     */
    public function parcelados(): array
    {
        return TransactionEloquent::query()
            // Eager load obrigatório (preventLazyLoading) e é ele que resolve a
            // competência pelo vencimento da fatura.
            ->with('creditCardBill')
            ->whereNotNull('installment_current')
            ->whereNotNull('installment_total')
            ->get()
            ->map(fn (TransactionEloquent $model): Transaction => $this->mapper->paraDominio($model))
            ->all();
    }

    /**
     * @return list<ReferenceMonth>
     */
    public function competenciasDisponiveis(): array
    {
        // COALESCE aplica a mesma regra de competência do mapper (vencimento
        // da fatura, ou a data do lançamento avulso) de forma portável entre
        // MySQL e SQLite; a agregação por mês é feita em PHP.
        $datas = TransactionEloquent::query()
            ->leftJoin('credit_card_bills as ccb', 'transactions.credit_card_bill_id', '=', 'ccb.id')
            ->selectRaw('COALESCE(ccb.due_date, transactions.transaction_date) AS competencia')
            ->distinct()
            ->pluck('competencia');

        $competencias = [];

        foreach ($datas as $data) {
            if ($data === null) {
                continue;
            }

            $mes = ReferenceMonth::fromDate(new DateTimeImmutable((string) $data));
            $competencias[$mes->chave()] = $mes;
        }

        ksort($competencias);

        return array_values($competencias);
    }
}
