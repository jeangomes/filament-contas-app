<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        //dd($data);
        $models = [];

        foreach ($data['transactions'] as $transaction) {
            if ($transaction['description'] === 'Outros') {
                $transaction['tipo'] = 'pagamento';
            }
            $model = static::getModel()::create($transaction);
            $models[] = $model;
        }

        return $models[0];
        //return static::getModel()::create($data);
    }
}
