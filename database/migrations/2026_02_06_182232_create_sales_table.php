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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('distribution_task_id')

                ->constrained('distribution_tasks')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->date('date');
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->enum('status', ['draft', 'confirmed','settled'])->default('draft');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
