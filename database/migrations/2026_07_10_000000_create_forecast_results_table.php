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
        Schema::create('forecast_results', function (Blueprint $table) {
            $table->id();
            $table->string('month')->unique();
            $table->date('month_date');
            $table->decimal('forecast_production_kg', 10, 2);
            $table->decimal('final_production_kg', 10, 2);
            $table->integer('batches');
            $table->decimal('monthly_capacity', 10, 2);
            $table->decimal('surplus', 10, 2);
            $table->string('schedule');
            $table->json('forecast_data');
            $table->json('stock_data');
            $table->json('final_data');
            $table->json('materials');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_results');
    }
};
