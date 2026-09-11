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
        Schema::table('transactions', function (Blueprint $table) {

            $table->foreign('expense_category_id')
                ->references('id')
                ->on('expense_categories')
                ->onDelete('cascade');

//            $table->foreignId('payer_user_id')
//                ->constrained('users');
//
//            $table->foreignId('responsible_user_id')
//                ->constrained('users');

            $table->enum('scope', ['shared', 'individual'])
                ->default('individual');

            $table->integer('installment_current')->nullable();
            $table->integer('installment_total')->nullable();

            $table->index(
                ['transaction_date', 'scope'],
                'transactions_date_scope_idx'
            );

            $table->index(
                ['who_paid', 'transaction_date'],
                'transactions_payer_date_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_date_scope_idx');
            $table->dropIndex('transactions_payer_date_idx');

            $table->dropForeign(['expense_category_id']);

            $table->dropColumn([
                //'payer_user_id',
                //'responsible_user_id',
                'scope',
                'installment_current',
                'installment_total',
            ]);
        });
    }
};
