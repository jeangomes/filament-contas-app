<?php

declare(strict_types=1);

namespace App\Services\Relatorio;

use App\Domain\Finance\Entities\Transaction;
use DateTimeImmutable;

/**
 * Um lançamento da competência como o relatório o mostra: a entidade de
 * domínio (de onde saem a rubrica e os valores) mais o que só serve à
 * exibição e o domínio não carrega.
 */
final readonly class LinhaDoRelatorio
{
    public function __construct(
        public Transaction       $lancamento,
        public int               $transactionId,
        // A data original da compra: no domínio, item de fatura só guarda a
        // competência (o vencimento).
        public DateTimeImmutable $dataDaCompra,
        public ?int              $faturaId,   // null = lançamento avulso
        public ?string           $fatura,     // "Nubank D · venc. 11/07/2026"
        public ?string           $categoria,
        public ?string           $parcela,    // "3/6"
        public ?string           $origem,     // coluna `origin` do avulso
    ) {
    }
}
