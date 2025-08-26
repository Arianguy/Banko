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
        Schema::create('mutual_fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mutual_fund_id')->constrained()->cascadeOnDelete();
            $table->enum('transaction_type', ['buy', 'sell', 'sip', 'dividend_reinvestment']);
            $table->decimal('units', 15, 6);
            $table->decimal('nav', 10, 4);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('folio_number')->nullable();
            $table->decimal('stamp_duty', 8, 2)->default(0);
            $table->decimal('transaction_charges', 8, 2)->default(0);
            $table->decimal('gst', 8, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('order_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'mutual_fund_id']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutual_fund_transactions');
    }
};
