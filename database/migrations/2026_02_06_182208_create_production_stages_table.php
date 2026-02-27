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
    {Schema::create('production_stages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('production_order_id')->constrained();
        $table->string('stage_name'); // تحضير، طبخ، تعبئة، تبريد، إنهاء
        $table->enum('status', ['pending', 'active', 'done'])->default('pending');
        $table->timestamp('start_date')->nullable();
        $table->timestamp('end_date')->nullable();
        $table->timestamps();
    });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_stages');
    }
};
