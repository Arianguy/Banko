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
        Schema::create('fixed_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('bank');
            $table->string('accountno');
            $table->decimal('principal_amt', 15, 2);
            $table->decimal('maturity_amt', 15, 2);
            $table->date('start_date');
            $table->date('maturity_date');
            $table->integer('term');
            $table->decimal('int_rate', 5, 2);
            $table->decimal('Int_amt', 15, 2)->nullable();
            $table->decimal('Int_year', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_deposits');
    }
};
