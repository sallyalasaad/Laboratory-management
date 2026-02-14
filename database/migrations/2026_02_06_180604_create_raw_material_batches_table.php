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
    {Schema::create('raw_material_batches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('raw_material_batches');
    }
};
