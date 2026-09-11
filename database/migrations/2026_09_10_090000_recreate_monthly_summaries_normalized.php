<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normaliza o fechamento mensal.
 *
 * A `monthly_summaries` antiga tinha uma coluna por participante (`d_*`, `j_*`)
 * e uma por categoria da casa, então cada participante ou categoria nova
 * custava migration. Agora são três tabelas: o mês, uma linha por participante
 * e uma linha por despesa da casa.
 *
 * ATENÇÃO: esta migration DESCARTA o conteúdo de `monthly_summaries`. Isso é
 * aceitável porque a tabela é cache recalculável — tudo é reconstruído das
 * transações com `monthly:calculate --all`. Confirmado 0 linhas no banco real
 * antes de escrever esta migration; em qualquer outro ambiente, conferir antes
 * de rodar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Filhas antes do pai: a FK impede o drop na ordem inversa. Fazer isso
        // aqui (e não só no down) mantém a migration repetível se ela falhar
        // no meio e precisar rodar de novo.
        Schema::dropIfExists('monthly_home_expenses');
        Schema::dropIfExists('monthly_participant_summaries');
        Schema::dropIfExists('monthly_summaries');

        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->id();

            // Competência: o mês que está sendo fechado (o do vencimento das
            // faturas). O "mês de referência" é derivado disso na exibição.
            // Unique garante um fechamento por mês e serve às buscas e à
            // ordenação da listagem.
            $table->date('competencia')->unique();

            $table->decimal('total_home_expenses', 10, 2)->default(0);
            $table->decimal('total_common_expenses', 10, 2)->default(0);

            // Acerto do mês: valor sempre > = 0, com o pagador em coluna
            // separada (quem recebe é o outro participante).
            $table->decimal('settlement_amount', 10, 2)->default(0);
            $table->enum('settlement_payer', ['D', 'J'])->nullable();

            // Único campo desta tabela que é informação do usuário, não do
            // cálculo, sobrevivendo ao recálculo.
            $table->boolean('difference_paid')->default(false);

            $table->timestamp('calculated_at')->nullable();
            $table->text('calculation_notes')->nullable();

            $table->timestamps();
        });

        Schema::create('monthly_participant_summaries', function (Blueprint $table) {
            $table->id();

            // constrained() já cria o índice da FK; o unique composto abaixo
            // cobre as buscas por (mês, participante).
            $table->foreignId('monthly_summary_id')->constrained()->cascadeOnDelete();
            $table->enum('participant', ['D', 'J']);

            $table->decimal('card_total', 10, 2)->default(0);
            $table->decimal('card_common', 10, 2)->default(0);
            $table->decimal('card_individual', 10, 2)->default(0);

            $table->decimal('paid_common', 10, 2)->default(0);
            $table->decimal('share_common', 10, 2)->default(0);
            $table->decimal('cross_debt_adjustment', 10, 2)->default(0); // pode ser negativo
            $table->decimal('balance', 10, 2)->default(0);               // pode ser negativo

            $table->decimal('living_cost', 10, 2)->default(0);

            $table->timestamps();

            // Impede dois fechamentos do mesmo participante no mesmo mês —
            // a rede de segurança contra recálculo duplicando linhas.
            // Nome explícito: o gerado automaticamente teria 67 caracteres e
            // estoura o limite de 64 do MySQL (o SQLite dos testes aceitaria).
            $table->unique(['monthly_summary_id', 'participant'], 'monthly_participant_summaries_unique');
        });

        Schema::create('monthly_home_expenses', function (Blueprint $table) {
            $table->id();

            // Sem índice extra em monthly_summary_id: constrained() já criou.
            $table->foreignId('monthly_summary_id')->constrained()->cascadeOnDelete();

            $table->string('label'); // a description da transação, sem lista fixa
            $table->decimal('amount', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_home_expenses');
        Schema::dropIfExists('monthly_participant_summaries');
        Schema::dropIfExists('monthly_summaries');

        // Restaura a estrutura antiga (vazia) para que o rollback deixe o
        // schema coerente com a migration de create original.
        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->id();

            $table->date('reference_month');
            $table->date('due_payment_month');

            $table->decimal('house_rental', 10, 2)->default(0);
            $table->decimal('condominium', 10, 2)->default(0);
            $table->decimal('eventual_apartment', 10, 2)->default(0);
            $table->decimal('electricity_bill', 10, 2)->default(0);
            $table->decimal('gas_bill', 10, 2)->default(0);
            $table->decimal('internet_bill', 10, 2)->default(0);

            $table->decimal('total_home_expenses', 10, 2)->default(0);
            $table->decimal('total_home_expenses_per_person', 10, 2)->default(0);

            $table->decimal('balance_difference', 10, 2)->default(0);
            $table->enum('balance_payer', ['D', 'J'])->nullable();

            $table->decimal('d_credit_card_total', 10, 2)->default(0);
            $table->decimal('d_credit_card_common', 10, 2)->default(0);
            $table->decimal('d_credit_card_individual', 10, 2)->default(0);
            $table->decimal('d_living_cost', 10, 2)->default(0);

            $table->decimal('j_credit_card_total', 10, 2)->default(0);
            $table->decimal('j_credit_card_common', 10, 2)->default(0);
            $table->decimal('j_credit_card_individual', 10, 2)->default(0);
            $table->decimal('j_living_cost', 10, 2)->default(0);

            $table->boolean('is_calculated')->default(false);
            $table->timestamp('calculated_at')->nullable();
            $table->text('calculation_notes')->nullable();
            $table->boolean('difference_paid')->default(false);

            $table->timestamps();

            $table->unique(['reference_month']);
            $table->index(['due_payment_month']);
            $table->index(['is_calculated']);
        });
    }
};
