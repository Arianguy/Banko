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
        Schema::create('user_dividend_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            $table->foreignId('dividend_payment_id')->constrained()->onDelete('cascade');
            $table->integer('qualifying_shares'); // Number of shares that qualify for dividend
            $table->decimal('total_dividend_amount', 12, 2); // Total dividend received
            $table->date('record_date'); // Date when holdings were verified
            $table->enum('status', ['qualified', 'received', 'credited'])->default('qualified');
            $table->date('received_date')->nullable(); // Date when dividend was actually received
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_id', 'stock_id']);
            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'dividend_payment_id']); // One record per user per dividend
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_dividend_records');
    }
};
