<?php

namespace App\Infra\Csv;

use DateTimeImmutable;
use App\Domain\Finance\Entities\Transaction;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Domain\FonteDeLancamentos;
use RuntimeException;

/**
 * Lê uma fatura em CSV e produz lançamentos de domínio.
 *
 * Trata os detalhes de infraestrutura do arquivo: BOM no cabeçalho, campos
 * entre aspas com escape "" (RFC 4180), linhas em branco/malformadas e a
 * conversão do valor no formato brasileiro. Os dados que não estão no CSV
 * (quem pagou, tipo padrão) são fornecidos como contexto da importação.
 */
final class LeitorDeFaturaCsv implements FonteDeLancamentos
{
    private const COLUNA_DATA = 'date';
    private const COLUNA_DESCRICAO = 'title';
    private const COLUNA_VALOR = 'amount';

    public function __construct(
        private readonly string $caminho,
        private readonly Participant $quemPagou,
        private readonly ExpenseScope $tipoPadrao,
        private readonly ConversorDeValorBrasileiro $conversor = new ConversorDeValorBrasileiro(),
    ) {
    }

    /**
     * @return list<Transaction>
     */
    public function obter(): array
    {
        $handle = @fopen($this->caminho, 'r');

        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir o CSV: $this->caminho");
        }

        try {
            $cabecalho = fgetcsv($handle, escape: '');

            if ($cabecalho === false) {
                return [];
            }

            $cabecalho = $this->removerBom($cabecalho);
            $lancamentos = [];

            while (($linha = fgetcsv($handle, escape: '')) !== false) {
                if ($this->linhaVazia($linha) || count($linha) !== count($cabecalho)) {
                    continue; // pula linha em branco ou com colunas faltando
                }

                /** @var array<string, string> $registro */
                $registro = array_combine($cabecalho, $linha);

                $lancamentos[] = new Transaction(
                    date: new DateTimeImmutable($registro[self::COLUNA_DATA]),
                    description: $registro[self::COLUNA_DESCRICAO],
                    amount: $this->conversor->converter($registro[self::COLUNA_VALOR]),
                    whoPaid: $this->quemPagou,
                    scope: $this->tipoPadrao,
                    responsible: null,
                );
            }

            return $lancamentos;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Remove o BOM UTF-8 que exportações de banco/Excel costumam colocar
     * no início do arquivo, evitando um primeiro cabeçalho tipo "\uFEFFdate".
     *
     * @param  list<string>  $cabecalho
     * @return list<string>
     */
    private function removerBom(array $cabecalho): array
    {
        if (isset($cabecalho[0])) {
            $cabecalho[0] = preg_replace('/^\x{FEFF}/u', '', $cabecalho[0]) ?? $cabecalho[0];
        }

        return $cabecalho;
    }

    /**
     * @param  list<string|null>  $linha
     */
    private function linhaVazia(array $linha): bool
    {
        return $linha === [null];
    }
}
