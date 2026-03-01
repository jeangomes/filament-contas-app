<?php

namespace Database\Seeders;

use App\Models\CreditCardBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

//        User::factory()->create([
//            'name' => 'Test User',
//            'email' => 'test@example.com',
//        ]);

        // Últimos 4 meses
        $qtd_month = 6;
        $months = collect(range(0, $qtd_month-1))->map(function ($i) {
            return now()->subMonths($i+2)->startOfMonth();
        });
        foreach ($months as $month) {
            Transaction::factory()->create([
                'transaction_date' => $month,
                'description' => 'Aluguel',
                'who_paid' => 'J',
                'common_expense' => true,
                'individual_expense' => false,
                'amount' => 1200
            ]);
            foreach (['D', 'J'] as $owner) {

                // Criar fatura do mês para o dono
                $bill = CreditCardBill::factory()->create([
                    'owner_bill' => $owner,
                    'due_date' => $month->copy()->addDays(10), // exemplo: dia 10 do mês seguinte
                    'title_description_owner' => "Fatura Fake NB $owner - " . $month->format('m/Y'),
                ]);

                // Criar itens de fatura (5 a 10 compras)
                $transactions = Transaction::factory()
                    ->count(rand(3, 3))
                    ->make()
                    ->each(function ($item) use ($bill, $owner) {

                        // Person who paid (quase sempre o dono da fatura)
                        $item->who_paid = $owner;

                        // 35% das despesas serão marcadas como comuns
                        $isCommon = fake()->boolean(50);

                        $item->common_expense = $isCommon;
                        $item->individual_expense = !$isCommon;

                        // Ajusta relação
                        $item->credit_card_bill_id = $bill->id;

                        $item->save();
                    });

                // Atualizar valores da fatura
                $bill->amount = $transactions->sum('amount');
                $bill->common_amount = $transactions->where('common_expense', true)->sum('amount');
                $bill->individual_amount = $transactions->where('individual_expense', true)->sum('amount');
                $bill->save();
            }
        }
    }
}
