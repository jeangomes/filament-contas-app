<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Finance\Enums\TransactionType;
use App\Models\CreditCardBill;
use App\Models\Transaction;

/**
 * Mantém atualizados os totais agregados da fatura
 * (`common_amount` / `individual_amount`), que são cache de exibição.
 *
 * Antes isto vivia em `Transaction::booted()` e reagia **só** a `updated`:
 * a importação de CSV dispara `created` e apagar dispara `deleted`, então os
 * totais só se acertavam se alguém editasse uma transação à mão depois.
 *
 * `amount` (o total informado da fatura) não é tocado de propósito: é
 * preenchido pelo usuário e serve de conferência contra a soma das
 * transações — divergência ali é informação, não ruído.
 *
 * Limitação conhecida: escrita em massa por query builder
 * (`Transaction::where(...)->delete()`) não dispara eventos de model e não
 * recalcula. Quem fizer isso precisa recalcular na mão.
 */
final class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->recalcular($transaction->credit_card_bill_id);
    }

    public function updated(Transaction $transaction): void
    {
        // Se a transação trocou de fatura, as duas ficaram desatualizadas.
        $anterior = $transaction->getOriginal('credit_card_bill_id');

        $this->recalcular($anterior);

        if ($anterior !== $transaction->credit_card_bill_id) {
            $this->recalcular($transaction->credit_card_bill_id);
        }
    }

    public function deleted(Transaction $transaction): void
    {
        // O id ainda está nos atributos do model apagado; a relação já não
        // resolveria.
        $this->recalcular($transaction->credit_card_bill_id);
    }

    private function recalcular(int|string|null $faturaId): void
    {
        if ($faturaId === null) {
            return; // lançamento avulso: não pertence a fatura nenhuma
        }

        // Uma consulta só para os dois totais, em vez de um SUM para cada.
        $totais = Transaction::query()
            ->where('credit_card_bill_id', $faturaId)
            ->where('type', '!=', TransactionType::BillPayment->value)
            ->selectRaw('COALESCE(SUM(CASE WHEN common_expense = 1 THEN amount END), 0) AS comum')
            ->selectRaw('COALESCE(SUM(CASE WHEN individual_expense = 1 THEN amount END), 0) AS individual')
            ->first();

        // Update pelo query builder: não carrega o model e não dispara os
        // eventos da fatura — senão cada transação importada geraria uma
        // entrada de "Alteração de Fatura" no log de atividades.
        CreditCardBill::query()
            ->whereKey($faturaId)
            ->update([
                'common_amount' => $totais->comum,
                'individual_amount' => $totais->individual,
            ]);
    }
}
