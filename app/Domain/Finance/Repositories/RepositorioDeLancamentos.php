<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

/**
 * Porta: fonte de lançamentos já persistidos, consultados por competência.
 * O domínio depende apenas desta abstração — quem resolve de onde os
 * lançamentos vêm (Eloquent, SQL, memória) é a camada de infraestrutura.
 *
 * Complementa a porta de importação {@see \App\Domain\FonteDeLancamentos},
 * que trata da entrada de dados novos (CSV, OFX, API).
 */
interface RepositorioDeLancamentos
{
    /**
     * @return list<Transaction>
     */
    public function paraCompetencia(ReferenceMonth $mes): array;

    /**
     * Meses distintos em que existe pelo menos um lançamento — usado para
     * reconstruir o relatório histórico que percorre todos os meses.
     *
     * @return list<ReferenceMonth>
     */
    public function competenciasDisponiveis(): array;
}
