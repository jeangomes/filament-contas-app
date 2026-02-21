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
        Schema::table('credit_card_bills', function (Blueprint $table) {
            $table->string('csv_file')->nullable();
            $table->string('pdf_file')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_card_bills', function (Blueprint $table) {
            $table->dropColumn('csv_file');
            $table->dropColumn('pdf_file');
        });
    }
};
