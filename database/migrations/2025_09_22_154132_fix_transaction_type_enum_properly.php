<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to modify the enum column properly
        DB::statement("ALTER TABLE mutual_fund_transactions MODIFY COLUMN transaction_type ENUM('buy', 'sell', 'sip', 'dividend_reinvestment', 'redemption') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE mutual_fund_transactions MODIFY COLUMN transaction_type ENUM('buy', 'sell', 'sip', 'dividend_reinvestment') NOT NULL");
    }
};
