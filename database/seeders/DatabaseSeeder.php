<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'phone' => '0000000000',
                'password' => Hash::make('superadmin123'),
                'role' => 'super_admin',
                'is_verified' => true
            ]
        );
        $superToken = $superAdmin->createToken('superadmin_token')->plainTextToken;

        echo "\n✅ Super Admin:\n";
        echo "Email: superadmin@example.com\n";
        echo "Phone: 0000000000\n";
        echo "Password: superadmin123\n";
        echo "Token: $superToken\n\n";

        // Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'phone' => '1111111111',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_verified' => true
            ]
        );
        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        echo "✅ Admin:\n";
        echo "Email: admin@example.com\n";
        echo "Phone: 1111111111\n";
        echo "Password: admin123\n";
        echo "Token: $adminToken\n\n";

        $this->command->info('✅ تم إنشاء حسابات Super Admin و Admin بنجاح');
    }
}
