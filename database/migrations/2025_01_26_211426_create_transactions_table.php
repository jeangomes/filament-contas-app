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
            $table->decimal('common_amount_divided_by_two', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id(); // ID único para a transação
            $table->unsignedBigInteger('credit_card_bill_id')->index()->nullable();
            $table->foreign('credit_card_bill_id')->references('id')->on('credit_card_bills')->onDelete('cascade');
            $table->date('transaction_date');
            // due_date - data de vencimento para as contas da casa, e a data de vencimento da fatura, se é tudo do mesmo mes, agrupa pelo mes para fazer o consolidado, do web.php
            $table->string('description'); // Descrição da transação
            $table->string('parcelas')->nullable(); // Parcelas (ex: '1/3') - Para transações parceladas
            $table->decimal('amount', 10, 2); // Valor da transação (com 2 casas decimais)

            $table->enum('owner_expense', ['D', 'J'])->nullable();
            $table->enum('who_paid', ['D', 'J'])->nullable();
            $table->boolean('common_expense')->nullable();
            $table->boolean('individual_expense')->nullable();

            $table->boolean('mov_type')->default(0); // 0 para débito/saida, 1 crédito/entrada/recebimento
            $table->enum('status', ['pendente', 'pago', 'vencido'])->default('pendente'); // Status de pagamento
            //$table->string('category')->nullable(); // Categoria da transação (ex: 'aluguel', 'consumo', 'salário', transporte/alimentação)
            $table->string('origin')->nullable(); // Conta origem (cartão de crédito, conta corrente, etc.)
            $table->enum('tipo', ['despesa', 'recebimento', 'pagamento'])->default('despesa'); // Tipo da transação

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
