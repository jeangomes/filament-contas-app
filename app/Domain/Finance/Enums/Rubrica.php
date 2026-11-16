<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

/**
 * O caminho que um lançamento percorre no fechamento do mês.
 *
 * As calculadoras decidem isso implicitamente, lançamento a lançamento; a
 * rubrica dá nome à decisão, para quem precisa explicá-la (o relatório da
 * competência). Quem classifica é {@see \App\Domain\Finance\Entities\Transaction::rubrica()}.
 */
enum Rubrica: string
{
    case ComumDeCartao = 'comum_cartao';
    case ComumDaCasa = 'comum_casa';
    case IndividualPropria = 'individual';
    case DividaCruzada = 'divida_cruzada';
    case ForaDoRateio = 'fora_do_rateio';

    public function rotulo(): string
    {
        return match ($this) {
            self::ComumDeCartao => 'Comum (cartão)',
            self::ComumDaCasa => 'Casa',
            self::IndividualPropria => 'Individual',
            self::DividaCruzada => 'Dívida cruzada',
            self::ForaDoRateio => 'Fora do rateio',
        };
    }

    public function explicacao(): string
    {
        return match ($this) {
            self::ComumDeCartao => 'Despesa comum lançada em fatura de cartão: entra no rateio em partes iguais.',
            self::ComumDaCasa => 'Despesa comum fora de cartão (despesa da casa): entra no rateio em partes iguais.',
            self::IndividualPropria => 'Despesa individual paga pelo próprio responsável: não entra no rateio, só no custo de vida dele.',
            self::DividaCruzada => 'Despesa individual paga por quem não é o responsável: quem pagou tem o valor a receber do responsável.',
            self::ForaDoRateio => 'Pagamento de fatura: não é despesa, não entra no rateio nem no custo de vida.',
        };
    }

    /**
     * Só as despesas comuns são rateadas entre os participantes.
     */
    public function entraNoRateio(): bool
    {
        return $this === self::ComumDeCartao || $this === self::ComumDaCasa;
    }

    /**
     * Tudo o que é despesa de fato — o que compõe o total gasto e o custo de
     * vida do mês.
     */
    public function contaComoGasto(): bool
    {
        return $this !== self::ForaDoRateio;
    }
}
