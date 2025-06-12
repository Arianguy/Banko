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
        Schema::create('dividend_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->date('ex_dividend_date'); // Date when stock must be owned to qualify
            $table->date('dividend_date'); // Date when dividend is paid
            $table->decimal('dividend_amount', 10, 4); // Dividend amount per share
            $table->string('dividend_type')->default('regular'); // regular, special, interim
            $table->text('announcement_details')->nullable();
            $table->timestamps();

            // Index for efficient queries
            $table->index(['stock_id', 'ex_dividend_date']);
            $table->index(['ex_dividend_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dividend_payments');
    }
};
