<?php

namespace App\Filament\Resources\CreditCardBills\Pages;

use App\Filament\Resources\CreditCardBills\CreditCardBillResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\CreditCardBill;
use Illuminate\Support\Facades\Storage;

class CreateCreditCardBill extends CreateRecord
{
    protected static string $resource = CreditCardBillResource::class;
    protected ?bool $hasDatabaseTransactions = true;

    protected function afterCreate(): void
    {
        /** @var CreditCardBill $bill */
        $bill = $this->record;
        if ($this->data['origin_format'] === 'CSV') {
            $resultado = $this->processarDespesasFromCSV($bill->owner_bill, $this->data['most_common_expenses']);
            foreach ($resultado as $item) {
                $bill->transactions()->create($item);
            }
        }/*else {
            $resultado = $this->processarDespesas($this->data['content_transaction'], $bill->owner_bill, $this->data['most_common_expenses']);
        }*/

    }

    private function processarDespesasFromCSV($who_paid, $most_common_expenses): array
    {
        $record = $this->record;

        $path = $record->csv_file; // ex: imports/csv/abc123.csv

        if (! Storage::disk('private')->exists($path)) {
            return [];
        }

        $fullPath = Storage::disk('private')->path($path);
        $data = [];
        if (($handle = fopen($fullPath, 'r')) !== false) {

            $header = fgetcsv($handle, 1000, ','); // primeira linha (cabeçalho)

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $item = array_combine($header, $row);
                $data[] = $item;
            }

            fclose($handle);
        }

        $default_common_expenses = (bool)$most_common_expenses;

        $resultado = [];
        foreach ($data as $item) {
            $resultado[] = [
                'transaction_date' => $item['date'],
                'description' => $item['title'],
                //'parcelas' => $matches[3] ?? null, // Parcelas (ex.: "1/3"), se existirem
                'amount' => $item['amount'],
                'individual_expense' => !$default_common_expenses,
                'common_expense' => $default_common_expenses,
                'who_paid' => $who_paid
            ];
        }

        return $resultado;
    }
}
