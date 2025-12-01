<?php

namespace App\Filament\Resources\MonthlySummaries\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MonthlySummaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('reference_month')
                    ->required(),
                DatePicker::make('due_payment_month')
                    ->required(),
                TextInput::make('house_rental')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('condominium')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('eventual_apartment')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('electricity_bill')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('gas_bill')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('internet_bill')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_home_expenses')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_home_expenses_per_person')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('balance_difference')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Select::make('balance_payer')
                    ->options(['D' => 'D', 'J' => 'J']),
                TextInput::make('d_credit_card_total')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('d_credit_card_common')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('d_credit_card_individual')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('d_living_cost')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('j_credit_card_total')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('j_credit_card_common')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('j_credit_card_individual')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('j_living_cost')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                Toggle::make('is_calculated')
                    ->required(),
                DateTimePicker::make('calculated_at'),
                Textarea::make('calculation_notes')
                    ->columnSpanFull(),
                Toggle::make('difference_paid')
                    ->required(),
            ]);
    }
}
