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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('name');
            $table->string('exchange'); // NSE, BSE
            $table->string('sector')->nullable();
            $table->string('industry')->nullable();
            $table->decimal('current_price', 15, 2)->nullable();
            $table->decimal('day_change', 15, 2)->nullable();
            $table->decimal('day_change_percent', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['symbol', 'exchange']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
