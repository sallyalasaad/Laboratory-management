<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('finished_product_batches', 'remaining_quantity')) {
            Schema::table('finished_product_batches', function (Blueprint $table) {
                $table->decimal('remaining_quantity', 15, 3)->nullable()->after('quantity');
            });

            DB::table('finished_product_batches')
                ->whereNull('remaining_quantity')
                ->update(['remaining_quantity' => DB::raw('quantity')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finished_product_batches', function (Blueprint $table) {
            if (Schema::hasColumn('finished_product_batches', 'remaining_quantity')) {
                $table->dropColumn('remaining_quantity');
            }
        });
    }
};
