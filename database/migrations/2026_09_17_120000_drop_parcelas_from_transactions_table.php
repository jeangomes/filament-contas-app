<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A coluna `parcelas` (string, "3/6") nunca chegou a ser preenchida: a
 * importação de CSV jamais escreveu nela. Quem passa a carregar o dado são
 * `installment_current` / `installment_total`, que já existiam vazias desde
 * 2026_08_27.
 *
 * ALTER dedicada de propósito: editar a migration de `create` não muda banco
 * já migrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('parcelas');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('parcelas')->nullable()->after('description');
        });
    }
};
