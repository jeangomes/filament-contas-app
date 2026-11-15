<?php

namespace App\Filament\Resources\CreditCardBills\Pages;

use App\Filament\Resources\CreditCardBills\CreditCardBillResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\CreditCardBill;
use Illuminate\Support\Facades\Storage;
use App\Domain\Finance\Enums\ExpenseScope;
use App\Domain\Finance\Enums\Participant;
use App\Infra\Csv\LeitorDeFaturaCsv;
use App\Infra\Eloquent\TransactionMapper;
use RuntimeException;

class CreateCreditCardBill extends CreateRecord
{
    protected static string $resource = CreditCardBillResource::class;
    protected ?bool $hasDatabaseTransactions = true;
    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        if (($this->data['origin_format'] ?? null) !== 'CSV') {
            return;
        }

        /** @var CreditCardBill $fatura */
        $fatura = $this->record;

        $fonte = new LeitorDeFaturaCsv(
            caminho: $this->caminhoAbsolutoDoCsv($fatura->csv_file),
            quemPagou: Participant::from($fatura->owner_bill),
            tipoPadrao: $this->data['most_common_expenses']
                ? ExpenseScope::Comum
                : ExpenseScope::Individual,
        );

        $mapper = new TransactionMapper();

        $atributos = array_map(
            $mapper->paraAtributos(...),
            $fonte->obter(),
        );

        if ($atributos !== []) {
            $fatura->transactions()->createMany($atributos);
        }
    }

    private function caminhoAbsolutoDoCsv(?string $caminho): string
    {
        // Como $hasDatabaseTransactions = true, lançar aqui reverte a criação
        // da fatura — evita fatura órfã sem lançamentos. Se preferir manter a
        // fatura, troque por uma Notification::make()-danger() e um return.
        if ($caminho === null || ! Storage::disk('private')->exists($caminho)) {
            throw new RuntimeException("CSV da fatura não encontrado: $caminho");
        }

        return Storage::disk('private')->path($caminho);
    }
}
