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
        Schema::create('mutual_funds', function (Blueprint $table) {
            $table->id();
            $table->string('scheme_code')->unique();
            $table->string('scheme_name');
            $table->string('fund_house');
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->decimal('current_nav', 10, 4)->nullable();
            $table->date('nav_date')->nullable();
            $table->decimal('expense_ratio', 5, 2)->nullable();
            $table->string('fund_type')->default('equity'); // equity, debt, hybrid
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutual_funds');
    }
};
