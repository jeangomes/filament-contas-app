<?php

namespace App\Filament\Resources\CreditCardBillResource\Pages;

use App\Filament\Resources\CreditCardBillResource;
use App\Models\CreditCardBill;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditCardBill extends CreateRecord
{
    protected static string $resource = CreditCardBillResource::class;
    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        //dd($data);
        $data['user_id'] = auth()->id();
        //dd($data);
        return $data;
    }

    private function processarDespesas($texto, $who_paid): array
    {
        // Quebra o texto em linhas
        $linhas = explode("\n", trim($texto));

        $resultado = [];

        foreach ($linhas as $linha) {
            //preg_match('/^(\d{2} \w{3}) (.+?)(?: - (\d+\/\d+))? (\d+,\d{2})$/', $linha, $matches);
            // Expressão regular ajustada
            //preg_match('/^(\d{2} \w{3}) (.+?)(?: - (\d+\/\d+))? (\d+,\d{2})$/', $linha, $matches);
            preg_match('/^(\d{2} \p{L}+) (.+?)\s*(?:- (\d+\/\d+))?\s*([\d.,]+)$/u', $linha, $matches);


            if ($matches) {
                $amount = floatval(str_replace(['.', ','], ['', '.'], $matches[4]));

                $resultado[] = [
                    'transaction_date' => $this->setTransactionDate($matches[1]), // Data no formato "DD MMM"
                    'description' => trim($matches[2]), // Descrição
                    'parcelas' => $matches[3] ?? null, // Parcelas (ex.: "1/3"), se existirem
                    'amount' => $amount, // floatval(str_replace(',', '.', $matches[4])), // Valor como float
                    'individual_expense' => true,
                    'common_expense' => false,
                    //'owner_expense' => 'D',
                    'who_paid'=> $who_paid
                ];
            }
        }

        return $resultado;
    }

    private function processarDespesasFromCSV($texto, $who_paid): array
    {
        //dd($texto, $who_paid);
        $csv = $texto;
        $lines = explode(PHP_EOL, trim($csv));
        $data = [];
        if (count($lines) > 1) {
            $header = str_getcsv(array_shift($lines)); // primeira linha como cabeçalho
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $row = str_getcsv($line);
                $item = array_combine($header, $row);
                // Chaves adicionais fixas
                //$item['common_expense'] = false;
                //$item['category'] = null;
                $data[] = $item;
            }
        }
        $resultado = [];
        foreach ($data as $item) {
            $resultado[] = [
                'transaction_date' => $item['date'],
                'description' => $item['title'],
                //'parcelas' => $matches[3] ?? null, // Parcelas (ex.: "1/3"), se existirem
                'amount' => $item['amount'], // floatval(str_replace(',', '.', $matches[4])), // Valor como float
                'individual_expense' => true,
                'common_expense' => false,
                //'owner_expense' => 'D',
                'who_paid'=> $who_paid
            ];
        }

        return $resultado;
    }

    protected function afterCreate(): void
    {
        /** @var CreditCardBill $bill */
        $bill = $this->record;
        if ($this->data['origin_format'] === 'CSV') {
            $resultado = $this->processarDespesasFromCSV($this->data['content_transaction'], $bill->owner_bill);
        } else {
            $resultado = $this->processarDespesas($this->data['content_transaction'], $bill->owner_bill);
        }
        foreach ($resultado as $item) {
            $bill->transactions()->create($item);
        }
    }

    private function setTransactionDate($value): string
    {
        $monthNames = [
            'JAN' => '01',
            'FEV' => '02',
            'MAR' => '03',
            'ABR' => '04',
            'MAI' => '05',
            'JUN' => '06',
            'JUL' => '07',
            'AGO' => '08',
            'SET' => '09',
            'OUT' => '10',
            'NOV' => '11',
            'DEZ' => '12',
        ];

        // Assume que o valor seja uma string como '04 ABR'
        preg_match('/(\d{2}) (\w{3})/', $value, $matches);
        $day = $matches[1];
        $month = $monthNames[strtoupper($matches[2])];
        $year = date('Y'); // Ano atual, ou pode ser um ano específico
        //dd($year);
        return "$year-$month-$day";
    }
}
