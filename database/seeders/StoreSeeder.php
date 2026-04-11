<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::insert([
            [
                'region_id' => 1,
                'name' => 'سوق الألبان المزة',
                'barcode' => 'ST001',
                'lat' => 33.5102,
                'lng' => 36.2784
            ],
            [
                'region_id' => 1,
                'name' => 'مؤسسة الحليب',
                'barcode' => 'ST002',
                'lat' => 33.5110,
                'lng' => 36.2790
            ],
            [
                'region_id' => 2,
                'name' => 'سمانة جرمانا 1',
                'barcode' => 'ST003',
                'lat' => 33.4833,
                'lng' => 36.3333
            ],
        ]);
    }
}
