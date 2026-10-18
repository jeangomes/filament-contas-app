<?php

declare(strict_types=1);

namespace App\Domain\Finance\ValueObjects;

use App\Domain\Finance\Enums\Participant;

/**
 * Uma compra parcelada vista por inteiro: a série que os lançamentos mensais
 * ("- Parcela 1/6", "2/6", ...) desenham ao longo das competências.
 *
 * O que se sabe, vem só das parcelas já lançadas; o que falta é projeção.
 */
final readonly class Parcelamento
{
    public function __construct(
        public string $descricao,                 // nome da série, sem o sufixo
        public Participant $quemPagou,
        public int $totalDeParcelas,
        public int $parcelasPagas,                // maior número de parcela observado
        public Money $valorDaParcela,             // o da parcela mais recente
        public Money $totalPago,                  // soma das parcelas distintas vistas
        public ReferenceMonth $primeiraCompetencia,
        public ReferenceMonth $ultimaCompetencia, // competência da parcela mais recente
    ) {
    }

    public function emAndamento(): bool
    {
        return $this->parcelasPagas < $this->totalDeParcelas;
    }

    public function parcelasRestantes(): int
    {
        return $this->totalDeParcelas - $this->parcelasPagas;
    }

    /**
     * Quanto ainda falta pagar. Projeção: o valor da parcela pode variar de um
     * mês para o outro (juros, IOF), e o que se tem é o da mais recente.
     */
    public function valorRestante(): Money
    {
        return Money::emCentavos($this->valorDaParcela->centavos() * $this->parcelasRestantes());
    }

    /**
     * A competência em que a série se encerra — uma parcela por mês a partir
     * da última observada.
     */
    public function competenciaFinal(): ReferenceMonth
    {
        return $this->competenciaDaParcela($this->totalDeParcelas);
    }

    /**
     * A competência prevista para uma parcela futura. Parcela já observada ou
     * anterior devolve a própria última competência conhecida — aqui só se
     * projeta para frente.
     */
    public function competenciaDaParcela(int $numero): ReferenceMonth
    {
        $mes = $this->ultimaCompetencia;

        for ($i = $this->parcelasPagas; $i < $numero; $i++) {
            $mes = $mes->proximoMes();
        }

        return $mes;
    }

    /**
     * A série ainda tem parcela a vencer, mas a próxima já deveria ter caído
     * antes da competência informada — sinal de que as faturas seguintes não
     * foram importadas, não de que a compra continua correndo.
     *
     * Sem isso uma série interrompida em 2025 apareceria hoje como compromisso
     * futuro com término no passado.
     */
    public function defasadaEm(ReferenceMonth $competenciaAtual): bool
    {
        return $this->emAndamento()
            && $this->competenciaDaParcela($this->parcelasPagas + 1)->ehAnteriorA($competenciaAtual);
    }

    public function rotulo(): string
    {
        return sprintf('%d/%d', $this->parcelasPagas, $this->totalDeParcelas);
    }

    /**
     * Identidade da série: a mesma compra no cartão da mesma pessoa. O total
     * de parcelas entra na chave porque duas compras de mesmo nome e planos
     * diferentes são compras diferentes.
     */
    public function chave(): string
    {
        return self::chaveDe($this->descricao, $this->quemPagou, $this->totalDeParcelas);
    }

    public static function chaveDe(string $descricao, Participant $quemPagou, int $totalDeParcelas): string
    {
        return $descricao . '|' . $quemPagou->value . '|' . $totalDeParcelas;
    }
}
