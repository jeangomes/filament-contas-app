<?php

namespace App\Domain;

use App\Domain\Finance\Entities\Transaction;

/**
 * Porta: qualquer fonte capaz de fornecer os lançamentos de uma fatura
 * (CSV, OFX, API, etc.). O domínio depende apenas desta abstração.
 */
interface FonteDeLancamentos
{
    /**
     * @return list<Transaction>
     */
    public function obter(): array;
}
