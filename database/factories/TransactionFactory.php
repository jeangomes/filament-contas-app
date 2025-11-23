<?php

namespace Database\Factories;

use App\Models\CreditCardBill;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $whoPaid = $this->faker->randomElement(['D', 'J']);

        $isCommon = $this->faker->boolean(30); // probabilidade de 30% ser despesa comum

        return [
            'credit_card_bill_id' => null, // definido pelo seeder

            'transaction_date' => $this->faker->date(),
            'description' => $this->faker->randomElement([
                'Supermercado', 'Gasolina', 'Restaurante', 'Farmácia',
                'Compra Online', 'Eletrônicos', 'Roupas', 'Serviços'
            ]),

            //'parcelas' => $this->faker->optional()->randomElement(['1/1', '1/3', '2/3', '3/10']),

            'amount' => $this->faker->randomFloat(0, 20, 300),

           // 'who_paid' => $whoPaid,

            //'common_expense' => $isCommon,
            //'individual_expense' => !$isCommon,

            'mov_type' => 0, // sempre débito para compras de cartão
            'status' => 'pendente',

            'expense_category_id' => null, // caso você crie categories depois, atualizo

            //'origin' => $this->faker->optional()->randomElement(['Cartão D', 'Cartão J', 'Conta Banco']),
            'type' => $this->faker->randomElement([
                'fixed_expense', 'variable_expense', 'payment', 'superfluous'
            ]),
        ];
    }
}
