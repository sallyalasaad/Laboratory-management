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
    {Schema::create('finished_product_batches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('finished_product_id')->constrained()->cascadeOnDelete();
        $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
        $table->string('batch_number');
        $table->decimal('quantity',10,2);
        $table->date('expiry_date')->nullable();
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finished_product_batches');
    }
};
