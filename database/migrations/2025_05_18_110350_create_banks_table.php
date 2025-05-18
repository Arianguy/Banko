<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    
        // Optional: Seed initial banks
        DB::table('banks')->insert([
            ['name' => 'SBI'],
            ['name' => 'ICICI'],
            ['name' => 'HDFC'],
        ]);
    }
};
