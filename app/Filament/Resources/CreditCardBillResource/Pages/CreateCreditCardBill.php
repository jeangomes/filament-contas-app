<?php

namespace App\Filament\Resources\CreditCardBillResource\Pages;

use App\Filament\Resources\CreditCardBillResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditCardBill extends CreateRecord
{
    protected static string $resource = CreditCardBillResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        //dd($data);
        $data['user_id'] = auth()->id();
        //dd($data);
        return $data;
    }

    private function processarDespesas($texto): array
    {
        // Quebra o texto em linhas
        $linhas = explode("\n", trim($texto));

        $resultado = [];

        foreach ($linhas as $linha) {
            //preg_match('/^(\d{2} \w{3}) (.+?)(?: - (\d+\/\d+))? (\d+,\d{2})$/', $linha, $matches);
            // Expressão regular ajustada
            preg_match('/^(\d{2} \w{3}) (.+?)(?: - (\d+\/\d+))? (\d+,\d{2})$/', $linha, $matches);

            if ($matches) {
                $resultado[] = [
                    'transaction_date' => $matches[1], // Data no formato "DD MMM"
                    'description' => trim($matches[2]), // Descrição
                    'parcelas' => $matches[3] ?? null, // Parcelas (ex.: "1/3"), se existirem
                    'amount' => floatval(str_replace(',', '.', $matches[4])), // Valor como float
                ];
            }
        }

        return $resultado;
    }

    protected function afterCreate(): void
    {
        $resultado = $this->processarDespesas($this->data['content_transaction']);
        foreach ($resultado as $item) {
            Transaction::create($item);
            //$this->record->transactions()->saveMany();
            //dump($item);
        }
        //dd($this->data,$this->record->exists,$this->record->id);
        // Runs after the form fields are saved to the database.
       // dd('salvou');
    }
}
