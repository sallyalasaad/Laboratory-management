<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1️⃣ إنشاء الأدوار أولاً
        $this->call(RoleSeeder::class);

        // 2️⃣ Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'phone' => '0000000000',
                'password' => Hash::make('superadmin123'),
                'is_verified' => true,
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // 3️⃣ Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'phone' => '1111111111',
                'password' => Hash::make('admin123'),
                'is_verified' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        $this->command->info('✅ تم إنشاء المستخدمين وربط الأدوار بنجاح');
        // 4️⃣ بيانات المواد الأولية الأساسية
        $this->call(RawMaterialSeeder::class);
    }
}
