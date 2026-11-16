<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Services\ApuradorDeFechamentoMensal;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Infra\Eloquent\RepositorioDeLancamentosEloquent;
use App\Infra\Eloquent\TransactionMapper;
use App\Models\MonthlySummary;
use App\Models\Transaction;
use App\Services\Relatorio\LinhaDoRelatorio;
use App\Services\Relatorio\RelatorioDaCompetencia;
use DateTimeImmutable;

/**
 * Monta o relatório de uma competência: os lançamentos, o fechamento apurado
 * ao vivo pelo domínio e o fechamento salvo, para comparar.
 *
 * É o par de leitura do {@see FechamentoMensalService}: não persiste nada e
 * não contém regra — a classificação é `Transaction::rubrica()` e os números
 * são do {@see ApuradorDeFechamentoMensal}.
 *
 * Depende do adapter concreto, não da porta: precisa dos models (data da
 * compra, fatura, categoria), que o domínio não carrega.
 */
final class RelatorioDaCompetenciaService
{
    public function __construct(
        private readonly RepositorioDeLancamentosEloquent $repositorio,
        private readonly TransactionMapper $mapper = new TransactionMapper(),
        private readonly ApuradorDeFechamentoMensal $apurador = new ApuradorDeFechamentoMensal(),
    ) {
    }

    public function gerar(ReferenceMonth $mes): RelatorioDaCompetencia
    {
        $models = $this->repositorio->consultaDaCompetencia($mes)
            ->with(['creditCardBill', 'expenseCategory'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $linhas = [];
        $lancamentos = [];

        foreach ($models as $model) {
            $linha = $this->paraLinha($model);

            $linhas[] = $linha;
            $lancamentos[] = $linha->lancamento;
        }

        return new RelatorioDaCompetencia(
            mes: $mes,
            fechamento: $this->apurador->apurar($mes, $lancamentos, Participant::cases()),
            linhas: $linhas,
            persistido: MonthlySummary::query()
                ->with('participantSummaries')
                ->whereDate('competencia', $mes->chave() . '-01')
                ->first(),
        );
    }

    private function paraLinha(Transaction $model): LinhaDoRelatorio
    {
        $fatura = $model->creditCardBill;

        return new LinhaDoRelatorio(
            lancamento: $this->mapper->paraDominio($model),
            transactionId: $model->id,
            dataDaCompra: DateTimeImmutable::createFromInterface($model->transaction_date),
            faturaId: $fatura?->id,
            fatura: $fatura === null
                ? null
                : sprintf('%s · venc. %s', $fatura->title_description_owner, $fatura->due_date->format('d/m/Y')),
            categoria: $model->expenseCategory?->description,
            parcela: $model->rotuloDaParcela(),
            origem: filled($model->origin) ? (string) $model->origin : null,
        );
    }
}
