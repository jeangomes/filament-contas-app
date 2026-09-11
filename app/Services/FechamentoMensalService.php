<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\Services\ApuradorDeFechamentoMensal;
use App\Domain\Finance\ValueObjects\FechamentoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Models\MonthlySummary;
use Illuminate\Support\Facades\DB;

/**
 * Apura um mês pelo domínio e persiste o resultado.
 *
 * O fechamento é cache recalculável: rodar de novo para a mesma competência
 * substitui os números, sem duplicar nada. Duas informações do usuário
 * sobrevivem ao recálculo por não serem calculadas — `difference_paid` e
 * `calculation_notes`.
 *
 * Tradução apenas: toda regra vive no domínio ({@see ApuradorDeFechamentoMensal}).
 */
final class FechamentoMensalService
{
    public function __construct(
        private readonly RepositorioDeLancamentos $repositorio,
        private readonly ApuradorDeFechamentoMensal $apurador = new ApuradorDeFechamentoMensal(),
    ) {
    }

    public function apurarEPersistir(ReferenceMonth $mes): MonthlySummary
    {
        $fechamento = $this->apurador->apurar(
            $mes,
            $this->repositorio->paraCompetencia($mes),
            Participant::cases(),
        );

        // Tudo ou nada: um recálculo interrompido no meio deixaria o mês com
        // os totais novos e as linhas filhas antigas.
        return DB::transaction(function () use ($fechamento): MonthlySummary {
            $resumo = $this->gravarOMes($fechamento);

            $this->regravarAsLinhasFilhas($resumo, $fechamento);

            return $resumo->load(['participantSummaries', 'homeExpenses']);
        });
    }

    private function gravarOMes(FechamentoMensal $fechamento): MonthlySummary
    {
        $acerto = $fechamento->acertos()[0] ?? null;

        return MonthlySummary::query()->updateOrCreate(
            // A unique em `competencia` é a rede de segurança contra dois
            // fechamentos do mesmo mês em execuções concorrentes.
            ['competencia' => $fechamento->mes()->chave() . '-01'],
            [
                'total_home_expenses' => $fechamento->totalCasa()->paraDecimal(),
                'total_common_expenses' => $fechamento->totalComum()->paraDecimal(),
                // Valor sempre positivo; quem paga fica na coluna ao lado.
                'settlement_amount' => ($acerto?->valor ?? Money::zero())->paraDecimal(),
                'settlement_payer' => $acerto?->paga,
                'calculated_at' => now(),
                // `difference_paid` e `calculation_notes` ficam de fora de
                // propósito: são do usuário, não do cálculo.
            ],
        );
    }

    private function regravarAsLinhasFilhas(MonthlySummary $resumo, FechamentoMensal $fechamento): void
    {
        // Apagar e recriar em vez de casar linha a linha: o fechamento é
        // derivado por inteiro das transações, então não há estado a
        // preservar nessas linhas.
        $resumo->participantSummaries()->delete();
        $resumo->homeExpenses()->delete();

        $participantes = [];
        foreach ($fechamento->participantes() as $participante) {
            $participantes[] = [
                'participant' => $participante->participante,
                'card_total' => $participante->cartaoTotal->paraDecimal(),
                'card_common' => $participante->cartaoComum->paraDecimal(),
                'card_individual' => $participante->cartaoIndividual->paraDecimal(),
                'paid_common' => $participante->pagoComum->paraDecimal(),
                'share_common' => $participante->cotaComum->paraDecimal(),
                'cross_debt_adjustment' => $participante->ajusteDividaCruzada->paraDecimal(),
                'balance' => $participante->saldo->paraDecimal(),
                'living_cost' => $participante->custoDeVida->paraDecimal(),
            ];
        }

        $despesasDaCasa = [];
        foreach ($fechamento->despesasDaCasa() as $despesa) {
            $despesasDaCasa[] = [
                'label' => $despesa->label,
                'amount' => $despesa->valor->paraDecimal(),
            ];
        }

        // createMany pela relação: a FK vem dela, não de atribuição em massa.
        $resumo->participantSummaries()->createMany($participantes);

        if ($despesasDaCasa !== []) {
            $resumo->homeExpenses()->createMany($despesasDaCasa);
        }
    }
}
