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
        Schema::create('waste', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finished_product_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity',10,2);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wastes');
    }
};
