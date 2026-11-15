<?php

declare(strict_types=1);

namespace App\Filament\Pages\Widgets;

use App\Domain\Finance\Enums\TransactionType;
use App\Domain\Finance\ValueObjects\ReferenceMonth;
use App\Filament\Resources\CreditCardBills\CreditCardBillResource;
use App\Models\CreditCardBill;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Os lançamentos da fatura de um participante num mês de vencimento, com os
 * totais — usado duas vezes, lado a lado, em
 * {@see \App\Filament\Pages\BillsSideBySideOverview}.
 *
 * Fica fora de `app/Filament/Widgets` de propósito: lá o painel descobre o
 * widget e o põe no Dashboard.
 *
 * Os totais são somados das transações, não lidos de `common_amount` /
 * `individual_amount` da fatura: aquele cache só é recalculado quando uma
 * transação é editada e fica zerado logo após a importação.
 */
class LancamentosDaFatura extends TableWidget
{
    /** Participante dono da fatura (D ou J). */
    public string $dono;

    /** Mês de vencimento, `Y-m`. */
    public string $mes;

    protected int|string|array $columnSpan = 'full';

    /**
     * Cache da fatura por request. Privado: o Livewire não serializa, e
     * `dono`/`mes` não mudam durante a vida do widget — trocar o mês recria o
     * widget (a chave inclui o mês).
     */
    private ?CreditCardBill $faturaEmCache = null;

    private bool $faturaCarregada = false;

    public function table(Table $table): Table
    {
        $comoNoCache = static fn (QueryBuilder $query): QueryBuilder => $query
            ->where('type', '!=', TransactionType::BillPayment->value);

        return $table
            ->heading("Fatura de $this->dono")
            ->description(fn (): string => ($fatura = $this->fatura()) === null
                ? 'nenhuma fatura neste mês'
                : 'vencimento ' . $fatura->due_date->format('d/m/Y'))
            ->query(fn (): Builder => Transaction::query()
                ->whereHas('creditCardBill', fn (Builder $fatura): Builder => $this->daFaturaDoMes($fatura))
                ->orderBy('transaction_date')
                ->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('description')
                    ->label('Descrição')
                    ->wrap()
                    ->description(fn (Transaction $record): ?string => $record->rotuloDaParcela() === null
                        ? null
                        : 'parcela ' . $record->rotuloDaParcela()),
                TextColumn::make('transaction_date')->label('Data')->date('d/m/Y'),
                IconColumn::make('individual_expense')->label('Individual')->boolean(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->alignEnd()
                    // Mesmo critério do TransactionObserver: pagamento da
                    // fatura não é despesa.
                    ->summarize([
                        Sum::make()
                            ->label('Total')
                            ->money('BRL')
                            ->query($comoNoCache),
                        Sum::make()
                            ->label('Comuns')
                            ->money('BRL')
                            ->query(fn (QueryBuilder $query): QueryBuilder => $comoNoCache($query)->where('common_expense', true)),
                        Sum::make()
                            ->label('Individuais')
                            ->money('BRL')
                            ->query(fn (QueryBuilder $query): QueryBuilder => $comoNoCache($query)->where('individual_expense', true)),
                    ]),
            ])
            ->headerActions([
                Action::make('abrir_fatura')
                    ->label('Abrir fatura')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): ?string => ($fatura = $this->fatura()) === null
                        ? null
                        : CreditCardBillResource::getUrl('edit', ['record' => $fatura]))
                    ->visible(fn (): bool => $this->fatura() !== null),
            ])
            ->emptyStateHeading('Nenhuma fatura encontrada para este mês.')
            ->emptyStateDescription(null)
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    /**
     * A fatura em si, para o vencimento e o link. Cache explícito em vez de
     * `#[Computed]`: sem propriedade mágica, a IDE enxerga o tipo e o cache
     * não depende de ler como propriedade em vez de chamar o método.
     */
    private function fatura(): ?CreditCardBill
    {
        if (! $this->faturaCarregada) {
            $this->faturaEmCache = $this->daFaturaDoMes(CreditCardBill::query())->first();
            $this->faturaCarregada = true;
        }

        return $this->faturaEmCache;
    }

    /**
     * @param  Builder<CreditCardBill>  $query
     * @return Builder<CreditCardBill>
     */
    private function daFaturaDoMes(Builder $query): Builder
    {
        $mes = ReferenceMonth::fromString($this->mes);

        return $query
            ->where('owner_bill', $this->dono)
            ->whereYear('due_date', $mes->ano())
            ->whereMonth('due_date', $mes->numeroDoMes());
    }
}
