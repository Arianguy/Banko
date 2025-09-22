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
        Schema::table('mutual_fund_transactions', function (Blueprint $table) {
            $table->enum('transaction_type', ['buy', 'sell', 'sip', 'dividend_reinvestment', 'redemption'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mutual_fund_transactions', function (Blueprint $table) {
            $table->enum('transaction_type', ['buy', 'sell', 'sip', 'dividend_reinvestment'])->change();
        });
    }
};
