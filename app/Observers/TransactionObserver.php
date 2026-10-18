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
 * **Recalcula só na edição (`updated`), por decisão.** Criar e apagar não
 * disparam recálculo: a importação de CSV insere a fatura inteira de uma vez e
 * não vale um SUM por linha inserida.
 *
 * A consequência a conhecer: logo depois de importar uma fatura os dois totais
 * ficam em zero, até que alguém edite alguma transação dela. Nenhum cálculo
 * depende disso — o fechamento mensal soma as transações pelo domínio
 * ({@see \App\Domain\Finance\Services\ApuradorDeFechamentoMensal}); estas
 * colunas só alimentam a tela de faturas lado a lado.
 *
 * `amount` (o total informado da fatura) não é tocado de propósito: é
 * preenchido pelo usuário e serve de conferência contra a soma das
 * transações — divergência ali é informação, não ruído.
 *
 * Limitação conhecida: escrita em massa por query builder
 * (`Transaction::where(...)->update(...)`) não dispara eventos de model e não
 * recalcula. Quem fizer isso precisa recalcular na mão.
 */
final class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        // Se a transação trocou de fatura, as duas ficaram desatualizadas.
        $anterior = $transaction->getOriginal('credit_card_bill_id');

        $this->recalcular($anterior);

        if ($anterior !== $transaction->credit_card_bill_id) {
            $this->recalcular($transaction->credit_card_bill_id);
        }
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
