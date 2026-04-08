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
    {Schema::create('finished_products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('size',8,4);  // 1، 3، 350
        $table->string('unit');   // كيلو / غرام
        $table->text('description')->nullable();
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finished_products');
    }
};
