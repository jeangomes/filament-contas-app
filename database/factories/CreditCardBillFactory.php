<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CreditCardBillFactory extends Factory
{
    public function definition(): array
    {
        $owner = $this->faker->randomElement(['D', 'J']);

        return [
            'title_description_owner' => "Fatura do cartão - " . $this->faker->monthName(),
            'owner_bill' => $this->faker->randomElement(['D', 'J']),
            'observation' => $this->faker->optional()->sentence(),

            'amount' => 0, // atualizado após criar transações
            'due_date' => $this->faker->dateTimeBetween('first day of next month', 'last day of next month'),

            'common_amount' => 0,
            'individual_amount' => 0,
        ];
    }
}
