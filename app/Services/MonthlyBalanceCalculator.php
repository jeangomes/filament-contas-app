<?php

namespace App\Services;

use App\Domain\Finance\Enums\Participant;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\Services\CalculadoraDeSaldoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\MonthlyBalance;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

/**
 * Relatório histórico de saldos: percorre todos os meses com lançamento e
 * apura o saldo de cada um.
 *
 * A regra de negócio em si vive em {@see CalculadoraDeSaldoMensal}; aqui só
 * sobra a orquestração e a montagem do formato de array que as páginas
 * legadas (ComputedResults) já consomem.
 */
class MonthlyBalanceCalculator
{
    public function __construct(
        private readonly RepositorioDeLancamentos $repositorio,
        private readonly CalculadoraDeSaldoMensal $calculadora = new CalculadoraDeSaldoMensal(),
    ) {
    }

    /**
     * @return list<array{month_year: string, participant: string, total_paid_common: float, share_common: float, balance: float}>
     */
    public function calculateFinalBalances(): array
    {
        $participantes = Participant::cases();
        $balances = [];

        foreach ($this->repositorio->competenciasDisponiveis() as $mes) {
            $saldoMensal = $this->calculadora->calcular(
                $mes,
                $this->repositorio->paraCompetencia($mes),
                $participantes,
            );

            foreach ($participantes as $participante) {
                // O relatório histórico sempre somou ao "total pago" o que a
                // pessoa adiantou de despesa individual do outro, para a linha
                // exibida fechar (pagou X - cota Y = saldo Z). O domínio mantém
                // os dois conceitos separados; a soma acontece só aqui.
                $totalPago = $saldoMensal->totalPagoComumDe($participante);
                $adiantado = $saldoMensal->ajusteDeDividaCruzadaDe($participante);

                $balances[] = [
                    'month_year' => $mes->chave(),
                    'participant' => $participante->value,
                    'total_paid_common' => $this->paraFloat(
                        $adiantado->ehPositivo() ? $totalPago->somar($adiantado) : $totalPago,
                    ),
                    'share_common' => $this->paraFloat($saldoMensal->cotaComumDe($participante)),
                    'balance' => $this->paraFloat($saldoMensal->saldoDe($participante)),
                ];
            }
        }

        return $balances;
    }

    /**
     * Apura um único mês, sem varrer o histórico inteiro.
     */
    public function saldoDoMes(string $chaveDoMes): MonthlyBalance
    {
        $mes = ReferenceMonth::fromString($chaveDoMes);

        return $this->calculadora->calcular(
            $mes,
            $this->repositorio->paraCompetencia($mes),
            Participant::cases(),
        );
    }

    /**
     * O domínio trabalha em centavos; as páginas de relatório esperam número
     * para formatar como moeda.
     */
    private function paraFloat(Money $valor): float
    {
        return (float) $valor->paraDecimal();
    }
}
