<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->id();

            // Período de referência
            $table->date('reference_month'); // YYYY-MM (ex: 2025-01-01)
            $table->date('due_payment_month'); // YYYY-MM (ex: 2025-02-01)

            // Despesas fixas da casa
            $table->decimal('house_rental', 10, 2)->default(0); // aluguel do apto
            $table->decimal('condominium', 10, 2)->default(0); // condominio do apto
            $table->decimal('eventual_apartment', 10, 2)->default(0); // eventuais do apto
            $table->decimal('electricity_bill', 10, 2)->default(0); // light - conta de energia
            $table->decimal('gas_bill', 10, 2)->default(0); // naturgy - conta de gas
            $table->decimal('internet_bill', 10, 2)->default(0); // claro - conta de internet

            // Totais calculados
            $table->decimal('total_home_expenses', 10, 2)->default(0); // total despesas casa
            $table->decimal('total_home_expenses_per_person', 10, 2)->default(0); // total despesas casa por 2

            // Diferença de pagamento
            $table->decimal('balance_difference', 10, 2)->default(0); // diferença a pagar
            $table->enum('balance_payer', ['D', 'J'])->nullable(); // quem paga

            // Dados do participante D
            $table->decimal('d_credit_card_total', 10, 2)->default(0); // D nb total
            $table->decimal('d_credit_card_common', 10, 2)->default(0); // d nb comum
            $table->decimal('d_credit_card_individual', 10, 2)->default(0); // d nb individual
            $table->decimal('d_living_cost', 10, 2)->default(0); // d custo de vida

            // Dados do participante J
            $table->decimal('j_credit_card_total', 10, 2)->default(0); // J nb total
            $table->decimal('j_credit_card_common', 10, 2)->default(0); // j nb comum
            $table->decimal('j_credit_card_individual', 10, 2)->default(0); // j nb individual
            $table->decimal('j_living_cost', 10, 2)->default(0); // j custo de vida

            // Metadados
            $table->boolean('is_calculated')->default(false); // indica se foi calculado automaticamente
            $table->timestamp('calculated_at')->nullable(); // quando foi calculado
            $table->text('calculation_notes')->nullable(); // observações do cálculo
            $table->boolean('difference_paid')->default(false); // indica se a diferença foi paga

            $table->timestamps();

            // Índices
            $table->unique(['reference_month']); // um registro por mês de referência
            $table->index(['due_payment_month']);
            $table->index(['is_calculated']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_summaries');
    }
};
