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
        Schema::create('car_stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained(); // السائق
            $table->foreignId('distribution_task_id')->nullable()->constrained();

            $table->enum('status', ['pending','active','closed'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_stocks');
    }
};
