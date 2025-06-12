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
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->enum('transaction_type', ['buy', 'sell', 'bonus']);
            $table->integer('quantity');
            $table->decimal('price_per_stock', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->date('transaction_date');
            $table->string('exchange'); // NSE, BSE
            $table->string('broker')->nullable();
            $table->decimal('brokerage', 15, 2)->default(0);
            $table->decimal('total_charges', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
