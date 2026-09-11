<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\Exceptions\CompetenciaInvalida;
use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Models\MonthlySummary;
use App\Services\FechamentoMensalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CalculateMonthlySummary extends Command
{
    /**
     * ATENÇÃO à mudança de semântica: o argumento é a COMPETÊNCIA (o mês que
     * está sendo fechado, o do vencimento das faturas), não mais o "mês de
     * referência". Quem rodava `monthly:calculate 2026-06` para fechar julho
     * passa a rodar `monthly:calculate 2026-07`. O formato é o mesmo, então o
     * erro passaria despercebido — daí o aviso aqui e no texto do argumento.
     */
    protected $signature = 'monthly:calculate
                           {competencia? : Competência a fechar no formato YYYY-MM — o mês do VENCIMENTO, não o de referência (padrão: mês atual)}
                           {--all : Recalcula todas as competências que tenham lançamento}';

    protected $description = 'Apura e persiste o fechamento mensal: rateio das despesas comuns, acerto e custo de vida';

    public function __construct(
        private readonly FechamentoMensalService $fechamento,
        private readonly RepositorioDeLancamentos $repositorio,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all') && $this->argument('competencia') !== null) {
            $this->components->error('Use uma competência OU --all, não os dois.');

            return self::FAILURE;
        }

        return $this->option('all')
            ? $this->fecharTodasAsCompetencias()
            : $this->fecharUmaCompetencia();
    }

    private function fecharUmaCompetencia(): int
    {
        $informada = $this->argument('competencia') ?? Carbon::now()->format('Y-m');

        try {
            // O value object já valida o formato — sem repetir regex aqui.
            $mes = ReferenceMonth::fromString($informada);
        } catch (CompetenciaInvalida $erro) {
            $this->components->error($erro->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Fechando a competência {$mes->chave()}...");

        $resumo = $this->fechamento->apurarEPersistir($mes);

        $this->newLine();
        $this->exibir($resumo);

        return self::SUCCESS;
    }

    private function fecharTodasAsCompetencias(): int
    {
        $competencias = $this->repositorio->competenciasDisponiveis();

        if ($competencias === []) {
            $this->components->warn('Nenhum lançamento encontrado — nada a fechar.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Recalculando %d competência(s)...', count($competencias)));

        $falhas = [];
        $barra = $this->output->createProgressBar(count($competencias));
        $barra->start();

        foreach ($competencias as $mes) {
            try {
                // Cada mês é uma transação independente no serviço: uma falha
                // isolada não desfaz nem impede os outros meses.
                $this->fechamento->apurarEPersistir($mes);
            } catch (Throwable $erro) {
                $falhas[$mes->chave()] = $erro->getMessage();
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        $sucessos = count($competencias) - count($falhas);
        $this->components->info("{$sucessos} de " . count($competencias) . ' competência(s) fechada(s).');

        if ($falhas === []) {
            return self::SUCCESS;
        }

        foreach ($falhas as $chave => $mensagem) {
            $this->components->error("{$chave}: {$mensagem}");
        }

        // Sai com falha para que cron/CI percebam o problema.
        return self::FAILURE;
    }

    private function exibir(MonthlySummary $resumo): void
    {
        // As relações já vêm carregadas do serviço — nada de consulta por linha.
        $this->line(sprintf(
            '<info>Competência:</info> %s   <info>(gastos de</info> %s<info>)</info>',
            $resumo->competencia->format('m/Y'),
            $resumo->reference_month->format('m/Y'),
        ));

        $this->line(sprintf(
            '<info>Total casa:</info> %s   <info>|   Total comum:</info> %s',
            $this->emReais($resumo->total_home_expenses),
            $this->emReais($resumo->total_common_expenses),
        ));

        $this->newLine();

        $this->table(
            ['Participante', 'Cartão', 'Comum', 'Individual', 'Custo de vida', 'Saldo'],
            $resumo->participantSummaries
                ->sortBy(fn ($linha): string => $linha->participant->value)
                ->map(fn ($linha): array => [
                    $linha->participant->value,
                    $this->emReais($linha->card_total),
                    $this->emReais($linha->card_common),
                    $this->emReais($linha->card_individual),
                    $this->emReais($linha->living_cost),
                    $this->emReais($linha->balance),
                ])
                ->all(),
        );

        // Mesmo texto que a interface mostra — uma frase só, num lugar só.
        $this->line("<info>Acerto:</info> {$resumo->balance_description}");

        if ($resumo->homeExpenses->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Despesa da casa', 'Valor'],
                $resumo->homeExpenses
                    ->map(fn ($despesa): array => [$despesa->label, $this->emReais($despesa->amount)])
                    ->all(),
            );
        }
    }

    /**
     * Só formatação de tela: o valor vem como string decimal do banco e não
     * volta para o cálculo depois daqui.
     */
    private function emReais(string $valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
