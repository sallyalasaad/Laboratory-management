<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {

        Store::insert([

            // 🟢 Cham City Center
            [
                'region_id' => 1, // كفرسوسة
                'name' => 'Cham City Center',
                'barcode' => 'MALL001',
                'lat' => 33.50067,
                'lng' => 36.27431,
                'type' => 'wholesale'
            ],

            // 🟢 Qasioun Mall
            [
                'region_id' => 2, // برزة
                'name' => 'Qasioun Mall',
                'barcode' => 'MALL002',
                'lat' => 33.54890,
                'lng' => 36.31381,
                'type' => 'wholesale'
            ],

            // 🟢 Malki Mall
            [
                'region_id' => 3, // المالكي
                'name' => 'Malki Mall',
                'barcode' => 'MALL003',
                'lat' => 33.51905,
                'lng' => 36.27187,
                'type' => 'wholesale'
            ],

            // 🟢 Town Center
            [
                'region_id' => 4, // صحنايا
                'name' => 'Town Center',
                'barcode' => 'MALL004',
                'lat' => 33.45788,
                'lng' => 36.27239,
                'type' => 'wholesale'
            ],

            // 🟢 Dmall
            [
                'region_id' => 5, // دمر
                'name' => 'Dmall',
                'barcode' => 'MALL005',
                'lat' => 33.51889,
                'lng' => 36.20806,
                'type' => 'wholesale'
            ],

            [
                'region_id' => 1,
                'name' => 'Al Sham Supermarket',
                'barcode' => 'RET001',
                'lat' => 33.50210,
                'lng' => 36.27610,
                'type' => 'retail'
            ],[
                'region_id' => 1,
                'name' => 'Fresh Mini Market',
                'barcode' => 'RET002',
                'lat' => 33.50355,
                'lng' => 36.27840,
                'type' => 'retail'
            ],
        ]);
    }
}
