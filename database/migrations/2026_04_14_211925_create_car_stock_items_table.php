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
    {Schema::create('car_stock_items', function (Blueprint $table) {
        $table->id();

        $table->foreignId('car_stock_id')->constrained()->cascadeOnDelete();
        $table->foreignId('finished_product_id')->constrained();
        $table->foreignId('finished_product_batch_id')->constrained();

        $table->decimal('quantity', 10, 2);
        $table->decimal('remaining_quantity', 10, 2);

        $table->timestamps();

        $table->unique(['car_stock_id','finished_product_batch_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_stock_items');
    }
};
