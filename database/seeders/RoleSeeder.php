<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;


class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'super_admin',
            'admin',
            'raw_storekeeper',
            'product_storekeeper',
            'accountant',
            'production_employee',
            'driver',
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role, 'guard_name' => 'sanctum'] // بدل api
            );
        }
    }
}

