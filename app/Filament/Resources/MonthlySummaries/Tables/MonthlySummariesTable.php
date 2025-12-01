<?php

namespace App\Filament\Resources\MonthlySummaries\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class MonthlySummariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)->recordAction(null)
            ->columns([
                TextColumn::make('reference_month')
                    ->label('Mês Ref')
                    ->dateTime('m/Y'),
                TextColumn::make('due_payment_month')
                    ->label(new HtmlString('Mês <br /> Venc.'))
                    ->dateTime('m/Y'),
                TextColumn::make('house_rental')
                    ->label('Aluguel')
                    ->money('BRL'),
                TextColumn::make('condominium')
                    ->label('Condomínio')
                    ->money('BRL'),
                TextColumn::make('eventual_apartment')
                    ->label('Eventuais')
                    ->money('BRL'),
                TextColumn::make('electricity_bill')
                    ->label('LIGHT')
                    ->money('BRL'),
                TextColumn::make('gas_bill')
                    ->label('Naturgy')
                    ->money('BRL'),
                TextColumn::make('internet_bill')
                    ->label('Claro')
                    ->money('BRL'),
                TextColumn::make('total_home_expenses')
                    ->label(new HtmlString('Total <br/> Casa'))
                    ->money('BRL'),
                TextColumn::make('total_home_expenses_per_person')
                    ->label(new HtmlString('Dividido <br /> por 2'))
                    ->money('BRL'),
                TextColumn::make('balance_difference')
                    ->label(new HtmlString('Diferença <br /> a pagar'))
                    ->money('BRL'),
                TextColumn::make('balance_payer')
                    ->label(new HtmlString('Quem <br /> paga'))
                    ->badge(),
//                TextColumn::make('d_credit_card_total')
//                    ->numeric()
//                    ->sortable(),
//                TextColumn::make('d_credit_card_common')
//                    ->numeric()
//                    ->sortable(),
//                TextColumn::make('d_credit_card_individual')
//                    ->numeric()
//                    ->sortable(),
//                TextColumn::make('d_living_cost')
//                    ->money()
//                    ->sortable(),
//                TextColumn::make('j_credit_card_total')
//                    ->numeric()
//                    ->sortable(),
//                TextColumn::make('j_credit_card_common')
//                    ->numeric()
//                    ->sortable(),
//                TextColumn::make('j_credit_card_individual')
//                    ->numeric()
//                    ->sortable(),
//                TextColumn::make('j_living_cost')
//                    ->money()
//                    ->sortable(),
//                IconColumn::make('is_calculated')
//                    ->boolean(),
//                TextColumn::make('calculated_at')
//                    ->dateTime()
//                    ->sortable(),
                IconColumn::make('difference_paid')
                    ->label(new HtmlString('Diferença <br /> paga'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ]);
    }
}
