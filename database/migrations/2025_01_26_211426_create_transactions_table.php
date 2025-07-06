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
        Schema::create('credit_card_bills', function (Blueprint $table) {
            $table->id();
            $table->string('title_description_owner');
            $table->enum('owner_bill', ['D', 'J'])->nullable();
            $table->string('observation')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->decimal('common_amount', 10, 2)->default(0);
            $table->decimal('individual_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_card_bill_id')->index()->nullable();
            $table->foreign('credit_card_bill_id')->references('id')->on('credit_card_bills')->onDelete('cascade');
            $table->date('transaction_date');
            $table->string('description');
            $table->string('parcelas')->nullable();
            $table->decimal('amount', 10, 2);

            $table->enum('who_paid', ['D', 'J'])->nullable();
            $table->boolean('common_expense')->nullable();
            $table->boolean('individual_expense')->nullable();

            $table->boolean('mov_type')->default(0); // 0 para débito/saida, 1 crédito/entrada/recebimento
            $table->enum('status', ['pendente', 'pago', 'vencido'])->default('pendente'); // Status de pagamento
            $table->string('category')->nullable()->index(); // Categoria da transação (ex: 'aluguel', 'consumo', 'salário', transporte/alimentação)
            $table->string('origin')->nullable(); // Conta origem (cartão de crédito, conta corrente, etc.)
            $table->enum('tipo', ['despesa_fixa', 'despesa_variable', 'pagamento', 'pgto_de_fatura'])->default('despesa');

            $table->timestamps(); // Marcas de tempo (created_at e updated_at)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('credit_card_bills');
    }
};
