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
        Schema::create('distribution_items', function (Blueprint $table) {
            Schema::create('distribution_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('distribution_task_id');
                $table->unsignedBigInteger('finished_product_batch_id');
                $table->decimal('quantity', 10, 2)->default(0);

                $table->foreign('distribution_task_id')
                    ->references('id')->on('distribution_tasks')
                    ->onDelete('cascade');

                $table->foreign('finished_product_batch_id')
                    ->references('id')->on('finished_product_batches')
                    ->onDelete('cascade');

                $table->timestamps();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_items');
    }
};
