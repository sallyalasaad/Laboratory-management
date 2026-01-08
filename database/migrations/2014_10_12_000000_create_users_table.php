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
    {Schema::create('users', function (Blueprint $table) {
        $table->id();

        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');

        $table->enum('role', [
            'super_admin',
            'admin',
            'raw_storekeeper',
            'product_storekeeper',
            'accountant',
            'production_employee',
            'driver'
        ]);

        $table->string('phone')->unique(); // لتسجيل الدخول بالرقم
        $table->boolean('is_verified')->default(false);
        $table->date('contract_start_date')->nullable();
        $table->date('contract_end_date')->nullable();
        $table->string('otp')->nullable();
        $table->string('otp_created_at')->nullable();

        $table->rememberToken();
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {        Schema::dropIfExists('users');

    }

};
