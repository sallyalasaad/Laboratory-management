<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('raw_material_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('raw_material_batches', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('expiry_date');
            }
            if (!Schema::hasColumn('raw_material_batches', 'remaining_quantity')) {
                $table->decimal('remaining_quantity', 18, 4)->default(0)->after('quantity');
            }
        });
    }

    public function down()
    {
        Schema::table('raw_material_batches', function (Blueprint $table) {
            if (Schema::hasColumn('raw_material_batches', 'received_at')) {
                $table->dropColumn('received_at');
            }
            if (Schema::hasColumn('raw_material_batches', 'remaining_quantity')) {
                $table->dropColumn('remaining_quantity');
            }
        });
    }
};
