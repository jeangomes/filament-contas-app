<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\ValueObjects\Parcela;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Backfill das colunas de parcela a partir da descrição já importada.
 *
 * Até agora a importação de CSV jogava o `title` inteiro em `description` e
 * ignorava o sufixo "- Parcela N/M", deixando `installment_current` e
 * `installment_total` vazias. Este comando recupera o histórico; da
 * importação nova em diante o {@see \App\Infra\Csv\LeitorDeFaturaCsv} já
 * preenche as colunas.
 *
 * Idempotente por construção: só toca linha com `installment_total` nula.
 */
class ExtrairParcelas extends Command
{
    protected $signature = 'transactions:extrair-parcelas
                           {--dry-run : Mostra o que seria alterado sem gravar nada}';

    protected $description = 'Preenche installment_current/installment_total a partir do sufixo "- Parcela N/M" da descrição';

    public function handle(): int
    {
        $simulacao = (bool) $this->option('dry-run');

        $preenchidas = 0;
        $ignoradas = 0;
        $amostra = [];

        Transaction::query()
            // Sem o total não dá para saber se a série acabou, então é ele que
            // marca a linha como pendente de backfill.
            ->whereNull('installment_total')
            // Filtro grosso no banco (portável entre MySQL e SQLite); quem
            // decide de fato é a regex do value object.
            ->where('description', 'like', '%Parcela%')
            ->orderBy('id')
            ->chunkById(500, function ($lancamentos) use (&$preenchidas, &$ignoradas, &$amostra, $simulacao): void {
                foreach ($lancamentos as $lancamento) {
                    $parcela = Parcela::extrairDe((string) $lancamento->description);

                    if ($parcela === null) {
                        $ignoradas++;

                        continue;
                    }

                    if (count($amostra) < 10) {
                        $amostra[] = [$lancamento->id, $lancamento->description, $parcela->rotulo()];
                    }

                    if (! $simulacao) {
                        $lancamento->installment_current = $parcela->atual();
                        $lancamento->installment_total = $parcela->total();

                        // saveQuietly: nada de monetário muda, então não vale
                        // acordar o observer dos totais da fatura nem encher o
                        // log de atividades.
                        $lancamento->saveQuietly();
                    }

                    $preenchidas++;
                }
            });

        if ($amostra !== []) {
            $this->table(['id', 'Descrição', 'Parcela'], $amostra);

            if ($preenchidas > count($amostra)) {
                $this->line(sprintf('<comment>... e mais %d linha(s).</comment>', $preenchidas - count($amostra)));
            }

            $this->newLine();
        }

        $this->components->info(sprintf(
            '%d linha(s) %s; %d com "Parcela" no texto mas sem o sufixo N/M.',
            $preenchidas,
            $simulacao ? 'seriam preenchidas' : 'preenchidas',
            $ignoradas,
        ));

        if ($simulacao) {
            $this->components->warn('Simulação (--dry-run): nada foi gravado.');
        }

        return self::SUCCESS;
    }
}
