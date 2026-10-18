<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\ValueObjects\CompromissoMensal;
use App\Domain\Finance\ValueObjects\Money;
use App\Domain\Finance\ValueObjects\Parcelamento;
use App\Domain\Finance\ValueObjects\ReferenceMonth;

/**
 * Reconstrói as compras parceladas a partir dos lançamentos mensais.
 *
 * Cada parcela chega como um lançamento independente, num mês diferente e com
 * descrição diferente ("- Parcela 1/6", "- Parcela 2/6"). O que amarra tudo é
 * a descrição da série, somada a quem pagou e ao tamanho do plano.
 *
 * Serviço puro: não conhece banco, Eloquent nem SQL.
 */
final class ApuradorDeParcelamentos
{
    /**
     * @param  list<Transaction>  $lancamentos  De qualquer competência — a série atravessa meses por definição.
     * @return list<Parcelamento>
     */
    public function apurar(array $lancamentos): array
    {
        /** @var array<string, array{lancamento: Transaction, parcelas: array<int, Transaction>}> $series */
        $series = [];

        foreach ($lancamentos as $lancamento) {
            $parcela = $lancamento->parcela();

            if ($parcela === null || ! $lancamento->contaParaBalanco()) {
                continue;
            }

            $chave = Parcelamento::chaveDe(
                $lancamento->descricaoDaSerie(),
                $lancamento->whoPaid(),
                $parcela->total(),
            );

            // Indexado pelo número da parcela: reimportar a mesma fatura
            // sobrescreve em vez de contar a parcela duas vezes.
            $series[$chave]['lancamento'] = $lancamento;
            $series[$chave]['parcelas'][$parcela->atual()] = $lancamento;
        }

        $parcelamentos = array_map($this->montar(...), array_values($series));

        // Em andamento primeiro; dentro de cada grupo, o que termina antes.
        usort($parcelamentos, static function (Parcelamento $a, Parcelamento $b): int {
            return [$a->emAndamento() ? 0 : 1, $a->competenciaFinal()->chave(), $a->descricao]
                <=> [$b->emAndamento() ? 0 : 1, $b->competenciaFinal()->chave(), $b->descricao];
        });

        return $parcelamentos;
    }

    /**
     * O que ainda vai cair, competência a competência, somando todas as séries
     * em andamento.
     *
     * `$aPartirDe` descarta parcelas projetadas para competências já passadas:
     * elas só aparecem quando o histórico está incompleto (a fatura seguinte
     * nunca foi importada) e não são compromisso futuro nenhum.
     *
     * @param  list<Parcelamento>  $parcelamentos
     * @return list<CompromissoMensal>
     */
    public function projetar(array $parcelamentos, ?ReferenceMonth $aPartirDe = null): array
    {
        /** @var array<string, array{mes: ReferenceMonth, valor: Money}> $porMes */
        $porMes = [];

        foreach ($parcelamentos as $parcelamento) {
            if (! $parcelamento->emAndamento()) {
                continue;
            }

            for ($numero = $parcelamento->parcelasPagas + 1; $numero <= $parcelamento->totalDeParcelas; $numero++) {
                $mes = $parcelamento->competenciaDaParcela($numero);

                if ($aPartirDe !== null && $mes->ehAnteriorA($aPartirDe)) {
                    continue;
                }

                $chave = $mes->chave();

                $porMes[$chave]['mes'] = $mes;
                $porMes[$chave]['valor'] = ($porMes[$chave]['valor'] ?? Money::zero())
                    ->somar($parcelamento->valorDaParcela);
            }
        }

        ksort($porMes);

        return array_values(array_map(
            static fn (array $linha): CompromissoMensal => new CompromissoMensal($linha['mes'], $linha['valor']),
            $porMes,
        ));
    }

    /**
     * @param  array{lancamento: Transaction, parcelas: array<int, Transaction>}  $serie
     */
    private function montar(array $serie): Parcelamento
    {
        $parcelas = $serie['parcelas'];
        ksort($parcelas);

        $numeros = array_keys($parcelas);
        $maiorNumero = (int) max($numeros);
        $maisRecente = $parcelas[$maiorNumero];

        $totalPago = Money::zero();

        foreach ($parcelas as $lancamento) {
            $totalPago = $totalPago->somar($lancamento->amount());
        }

        // A primeira competência é a da menor parcela observada, que nem
        // sempre é a mais antiga em data se o histórico estiver incompleto.
        $primeira = $parcelas[(int) min($numeros)]->referenceMonth();

        return new Parcelamento(
            descricao: $serie['lancamento']->descricaoDaSerie(),
            quemPagou: $serie['lancamento']->whoPaid(),
            totalDeParcelas: $serie['lancamento']->parcela()->total(),
            parcelasPagas: $maiorNumero,
            valorDaParcela: $maisRecente->amount(),
            totalPago: $totalPago,
            primeiraCompetencia: $primeira,
            ultimaCompetencia: $maisRecente->referenceMonth(),
        );
    }
}
