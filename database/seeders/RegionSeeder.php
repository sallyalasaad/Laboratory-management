<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        Region::insert([
            ['name' => 'كفرسوسة'],
            ['name' => 'برزة'],
            ['name' => 'المالكي'],
            ['name' => 'صحنايا'],
            ['name' => 'دمر'],
        ]);
    }
}
