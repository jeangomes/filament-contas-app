<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        if ($schema->getOperation() === 'create') {
            return $schema
                ->schema([
                    Repeater::make('transactions')
                        ->schema([
                            self::fieldDescription(),
                            self::fieldOrigin(),
                            self::fieldAmount(),
                            self::fieldTransactionDate(),
                            self::fieldWhoPaid(),
                            self::fieldCommon(),
                            self::fieldIndividual(),
                            self::fieldResponsible(),
                        ])->cloneable()->addActionLabel('Add transação')
                        ->columns(3),

                ])->columns(1);
        }
        return $schema
            ->schema([
                self::fieldDescription(),
                self::fieldOrigin(),
                self::fieldAmount(),
                self::fieldTransactionDate(),
                self::fieldWhoPaid(),
                self::fieldCommon(),
                self::fieldIndividual(),
                self::fieldResponsible(),
            ])
            ->columns(3);
//        return $schema
//            ->components([
//                Select::make('credit_card_bill_id')
//                    ->relationship('creditCardBill', 'id'),
//                DatePicker::make('transaction_date')
//                    ->required(),
//                TextInput::make('description')
//                    ->required(),
//                TextInput::make('parcelas'),
//                TextInput::make('amount')
//                    ->required()
//                    ->numeric(),
//                Select::make('responsible_for_expense')
//                    ->options(['D' => 'D', 'J' => 'J']),
//                Select::make('who_paid')
//                    ->options(['D' => 'D', 'J' => 'J']),
//                Toggle::make('common_expense'),
//                Toggle::make('individual_expense'),
//                Toggle::make('mov_type')
//                    ->required(),
//                Select::make('status')
//                    ->options(['pendente' => 'Pendente', 'pago' => 'Pago', 'vencido' => 'Vencido'])
//                    ->default('pendente')
//                    ->required(),
//                TextInput::make('expense_category_id')
//                    ->numeric(),
//                TextInput::make('origin'),
//                Select::make('type')
//                    ->options([
//            'fixed_expense' => 'Fixed expense',
//            'variable_expense' => 'Variable expense',
//            'payment' => 'Payment',
//            'superfluous' => 'Superfluous',
//            'pgto_de_fatura' => 'Pgto de fatura',
//        ])
//                    ->default('fixed_expense')
//                    ->required(),
//            ]);
    }

    public static function fieldIndividual(): Radio
    {
        return Radio::make('individual_expense')->label('Individual')
            ->boolean()
            ->inline()
            ->default(false)
            ->required();
    }

    public static function fieldCommon(): Radio
    {
        return Radio::make('common_expense')->label('Em comum')
            ->boolean()
            ->inline()
            ->default(true)
            ->required();
    }

    /**
     * @return Radio
     */
    public static function fieldWhoPaid(): Radio
    {
        return Radio::make('who_paid')->label('Quem pagou')
            ->options([
                'D' => 'D',
                'J' => 'J',
            ])
            ->default('J')
            ->inline()
            ->required();
    }

    /**
     * @return Radio
     */
    public static function fieldResponsible(): Radio
    {
        return Radio::make('responsible_for_expense')
            ->label('Responsável/Devedor')
            ->options([
                'D' => 'D',
                'J' => 'J',
            ])->columnSpan(2)
            ->inline();
    }

    /**
     * @return DatePicker
     */
    public static function fieldTransactionDate(): DatePicker
    {
        return DatePicker::make('transaction_date')->label('Data')
            ->required();
    }

    /**
     * @return TextInput
     */
    public static function fieldAmount(): TextInput
    {
        return TextInput::make('amount')->label('Valor')
            ->numeric()
            ->inputMode('decimal')
            ->required();
    }

    /**
     * @return TextInput
     */
    public static function fieldOrigin(): TextInput
    {
        return TextInput::make('origin')->label('Origem')
            ->required(fn(Get $get): bool => $get('description') === 'Outros')
            ->visible(fn(Get $get): bool => $get('description') === 'Outros');
    }

    /**
     * @return Select
     */
    public static function fieldDescription(): Select
    {
        return Select::make('description')->label('Descrição')
            ->required()
            ->options([
                'Aluguel' => 'Aluguel',
                'Condomínio' => 'Condomínio',
                'Eventualidades' => 'Eventualidades (iptu, taxas)',
                'LIGHT' => 'LIGHT',
                'Naturgy' => 'Naturgy',
                'Claro' => 'Claro',
                'Outros' => 'Outros gastos (não fixos)',
            ])->live();
    }
}
