<?php

namespace App\Filament\Resources\MonthlySummaries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MonthlySummaryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference_month')
                    ->date(),
                TextEntry::make('due_payment_month')
                    ->date(),
                TextEntry::make('house_rental')
                    ->numeric(),
                TextEntry::make('condominium')
                    ->numeric(),
                TextEntry::make('eventual_apartment')
                    ->numeric(),
                TextEntry::make('electricity_bill')
                    ->numeric(),
                TextEntry::make('gas_bill')
                    ->numeric(),
                TextEntry::make('internet_bill')
                    ->numeric(),
                TextEntry::make('total_home_expenses')
                    ->numeric(),
                TextEntry::make('total_home_expenses_per_person')
                    ->numeric(),
                TextEntry::make('balance_difference')
                    ->numeric(),
                TextEntry::make('balance_payer')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('d_credit_card_total')
                    ->numeric(),
                TextEntry::make('d_credit_card_common')
                    ->numeric(),
                TextEntry::make('d_credit_card_individual')
                    ->numeric(),
                TextEntry::make('d_living_cost')
                    ->money(),
                TextEntry::make('j_credit_card_total')
                    ->numeric(),
                TextEntry::make('j_credit_card_common')
                    ->numeric(),
                TextEntry::make('j_credit_card_individual')
                    ->numeric(),
                TextEntry::make('j_living_cost')
                    ->money(),
                IconEntry::make('is_calculated')
                    ->boolean(),
                TextEntry::make('calculated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('calculation_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('difference_paid')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
