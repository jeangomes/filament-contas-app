<?php

declare(strict_types=1);

namespace App\Infra\Excel;

use App\Domain\Finance\Enums\Rubrica;
use App\Domain\Finance\ValueObjects\FechamentoDoParticipante;
use App\Domain\Finance\ValueObjects\Money;
use App\Services\Relatorio\LinhaDoRelatorio;
use App\Services\Relatorio\RelatorioDaCompetencia;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Grava o relatório de uma competência em `.xlsx`, em abas: Resumo,
 * Lançamentos, Dívidas cruzadas e Casa.
 *
 * Só traduz: recebe o {@see RelatorioDaCompetencia} pronto — o mesmo da tela
 * — e não consulta nem calcula nada.
 *
 * Sem fórmulas, de propósito: os valores são os do domínio (a cota com
 * centavo de resto não sai de `=total/2`).
 */
final class ExportadorDoRelatorioXlsx
{
    private const FORMATO_DE_MOEDA = '#,##0.00';

    private const FORMATO_DE_DATA = 'dd/mm/yyyy';

    public function gravar(RelatorioDaCompetencia $relatorio, string $caminho): void
    {
        $writer = new Writer();
        $writer->openToFile($caminho);

        $this->abaResumo($writer, $relatorio);
        $this->abaLancamentos($writer, $relatorio);
        $this->abaDividasCruzadas($writer, $relatorio);
        $this->abaCasa($writer, $relatorio);

        $writer->close();
    }

    private function abaResumo(Writer $writer, RelatorioDaCompetencia $relatorio): void
    {
        $aba = $writer->getCurrentSheet();
        $aba->setName('Resumo');
        $aba->setColumnWidth(46, 1);
        $aba->setColumnWidth(18, 2, 3, 4);

        $fechamento = $relatorio->fechamento;
        $participantes = $fechamento->participantes();
        $nomes = array_map(static fn (FechamentoDoParticipante $p): string => $p->participante->value, $participantes);
        $acerto = $fechamento->acertos()[0] ?? null;
        $salvo = $relatorio->persistido;

        /** @var Closure(Closure(FechamentoDoParticipante): Money): list<Money> $porParticipante */
        $porParticipante = static fn (Closure $valor): array => array_map($valor, $participantes);

        $this->titulo($writer, 'Relatório da competência');
        $this->linha($writer, 'Competência (mês do vencimento)', $this->competencia($relatorio->mes->chave()));
        $this->linha($writer, 'Mês dos gastos', $this->competencia($relatorio->mes->mesAnterior()->chave()));
        $this->linha($writer, 'Gerado em', new DateTimeImmutable('now'));
        $this->linha($writer, 'Fechamento salvo', match (true) {
            $salvo === null => 'Ainda não fechado',
            $relatorio->divergeDoPersistido() => 'Desatualizado — recalcule',
            default => 'Em dia',
        });
        if ($salvo !== null) {
            $this->linha($writer, 'Acerto pago?', $salvo->difference_paid ? 'Sim' : 'Não');
        }
        $this->emBranco($writer);

        $this->titulo($writer, 'Totais');
        $this->linha($writer, 'Total gasto (comuns + individuais)', $relatorio->totalGasto());
        $this->linha($writer, 'Total comum (o que é rateado)', $fechamento->totalComum());
        $this->linha($writer, 'Fora do rateio (pagamento de fatura)', $relatorio->totalDa(Rubrica::ForaDoRateio));
        $this->emBranco($writer);

        $this->titulo($writer, 'Passo 1 — Rateio das despesas comuns', ...$nomes);
        $this->linha($writer, 'Pagou de comum', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->pagoComum));
        $this->linha($writer, 'Cota (total comum ÷ ' . count($participantes) . ')', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->cotaComum));
        $this->linha($writer, 'Diferença (pagou − cota)', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->pagoComum->subtrair($p->cotaComum)));
        $this->emBranco($writer);

        $this->titulo($writer, 'Passo 2 — Dívidas cruzadas', ...$nomes);
        $this->linha($writer, 'Ajuste (adiantou pelo outro − foi adiantado)', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->ajusteDividaCruzada));
        $this->emBranco($writer);

        $this->titulo($writer, 'Passo 3 — Saldo e acerto', ...$nomes);
        $this->linha($writer, 'Saldo (diferença + ajuste)', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->saldo));
        $this->linha($writer, 'Quem paga', $acerto?->paga->value ?? '—');
        $this->linha($writer, 'Quem recebe', $acerto?->recebe->value ?? '—');
        $this->linha($writer, 'Valor do acerto', $acerto?->valor ?? Money::zero());
        $this->emBranco($writer);

        $this->titulo($writer, 'Custo de vida', ...$nomes);
        $this->linha($writer, 'Cota do comum', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->cotaComum));
        $this->linha($writer, 'Individuais de sua responsabilidade', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $relatorio->individuaisDe($p->participante)));
        $this->linha($writer, 'Custo de vida', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->custoDeVida));
        $this->emBranco($writer);

        $this->titulo($writer, 'Origem dos gastos', ...$nomes);
        $this->linha($writer, 'Cartão — total', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->cartaoTotal));
        $this->linha($writer, 'Cartão — comum', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->cartaoComum));
        $this->linha($writer, 'Cartão — individual', ...$porParticipante(static fn (FechamentoDoParticipante $p): Money => $p->cartaoIndividual));
        $this->linha($writer, 'Despesas da casa (comuns fora de cartão)', $fechamento->totalCasa());
        $this->linha($writer, 'Individuais avulsas', $relatorio->individuaisAvulsas());
        $this->emBranco($writer);

        $this->titulo($writer, 'Conferências', 'Lado esquerdo', 'Lado direito', 'Resultado');
        foreach ($relatorio->conferencias() as $conferencia) {
            $this->linha(
                $writer,
                $conferencia->regra,
                $conferencia->esquerda,
                $conferencia->direita,
                $conferencia->fecha() ? 'OK' : 'DIVERGE',
            );
        }
    }

    private function abaLancamentos(Writer $writer, RelatorioDaCompetencia $relatorio): void
    {
        $cabecalho = [
            'Data da compra', 'Descrição', 'Parcela', 'Origem', 'Categoria',
            'Pagou', 'Responsável', 'Rubrica', 'Entra no rateio', 'Valor',
        ];

        $aba = $writer->addNewSheetAndMakeItCurrent();
        $aba->setName('Lançamentos');
        $aba->setSheetView((new SheetView())->setFreezeRow(2));
        $aba->setAutoFilter(new AutoFilter(0, 1, count($cabecalho) - 1, count($relatorio->linhas) + 1));
        $aba->setColumnWidth(14, 1, 3);
        $aba->setColumnWidth(44, 2);
        $aba->setColumnWidth(32, 4);
        $aba->setColumnWidth(18, 5, 8, 9);
        $aba->setColumnWidth(12, 6, 7);
        $aba->setColumnWidth(14, 10);

        $this->titulo($writer, ...$cabecalho);

        foreach ($relatorio->linhas as $linha) {
            $lancamento = $linha->lancamento;
            $rubrica = $lancamento->rubrica();
            $individual = $rubrica->contaComoGasto() && ! $rubrica->entraNoRateio();

            $this->linha(
                $writer,
                $linha->dataDaCompra,
                $lancamento->description(),
                $linha->parcela ?? '',
                $linha->fatura ?? ('Avulso' . ($linha->origem === null ? '' : ' · ' . $linha->origem)),
                $linha->categoria ?? '',
                $lancamento->whoPaid()->value,
                $individual ? $lancamento->responsavelEfetivo()->value : '',
                $rubrica->rotulo(),
                $rubrica->entraNoRateio() ? 'Sim' : 'Não',
                $lancamento->amount(),
            );
        }
    }

    private function abaDividasCruzadas(Writer $writer, RelatorioDaCompetencia $relatorio): void
    {
        $aba = $writer->addNewSheetAndMakeItCurrent();
        $aba->setName('Dívidas cruzadas');
        $aba->setColumnWidth(14, 1);
        $aba->setColumnWidth(44, 2);
        $aba->setColumnWidth(14, 3, 4, 5);

        $this->titulo($writer, 'Data da compra', 'Descrição', 'Quem pagou', 'Responsável', 'Valor');

        foreach ($relatorio->linhasDa(Rubrica::DividaCruzada) as $linha) {
            $this->linha(
                $writer,
                $linha->dataDaCompra,
                $linha->lancamento->description(),
                $linha->lancamento->whoPaid()->value,
                $linha->lancamento->responsavelEfetivo()->value,
                $linha->lancamento->amount(),
            );
        }

        $this->emBranco($writer);
        $this->titulo($writer, 'Ajuste líquido', 'Participante', 'Valor');

        foreach ($relatorio->fechamento->participantes() as $participante) {
            $this->linha($writer, '', $participante->participante->value, $participante->ajusteDividaCruzada);
        }
    }

    private function abaCasa(Writer $writer, RelatorioDaCompetencia $relatorio): void
    {
        $aba = $writer->addNewSheetAndMakeItCurrent();
        $aba->setName('Casa');
        $aba->setColumnWidth(44, 1);
        $aba->setColumnWidth(16, 2);

        $this->titulo($writer, 'Descrição', 'Valor');

        foreach ($relatorio->fechamento->despesasDaCasa() as $despesa) {
            $this->linha($writer, $despesa->label, $despesa->valor);
        }

        $this->titulo($writer, 'Total', $relatorio->fechamento->totalCasa());
    }

    private function titulo(Writer $writer, string|Money ...$valores): void
    {
        $this->escrever($writer, $valores, negrito: true);
    }

    private function linha(Writer $writer, string|Money|DateTimeInterface ...$valores): void
    {
        $this->escrever($writer, $valores, negrito: false);
    }

    private function emBranco(Writer $writer): void
    {
        $writer->addRow(Row::fromValues(['']));
    }

    /**
     * @param  array<int|string, string|Money|DateTimeInterface>  $valores
     */
    private function escrever(Writer $writer, array $valores, bool $negrito): void
    {
        $celulas = [];

        foreach ($valores as $valor) {
            $estilo = new Style();

            if ($negrito) {
                $estilo->setFontBold();
            }

            if ($valor instanceof Money) {
                // Única passagem por float do projeto, e só na borda: o xlsx
                // guarda número como double. Nada é calculado com ele.
                $estilo->setFormat(self::FORMATO_DE_MOEDA);
                $valor = (float) $valor->paraDecimal();
            } elseif ($valor instanceof DateTimeInterface) {
                $estilo->setFormat(self::FORMATO_DE_DATA);
            }

            $celulas[] = Cell::fromValue($valor, $estilo);
        }

        $writer->addRow(new Row($celulas));
    }

    private function competencia(string $chave): string
    {
        [$ano, $mes] = explode('-', $chave);

        return "$mes/$ano";
    }
}
