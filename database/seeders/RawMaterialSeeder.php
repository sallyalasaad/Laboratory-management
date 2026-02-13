<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            ['name' => 'Milk', 'unit' => 'liter', 'description' => 'Raw milk for use in products'],
            ['name' => 'Butter', 'unit' => 'kg', 'description' => 'Natural butter'],
            ['name' => 'Qashqwan', 'unit' => 'box', 'description' => 'Qashwan additive (food additive)'],
            ['name' => 'Preservatives', 'unit' => 'kg', 'description' => 'Preservative mixture'],
            ['name' => 'Flavor', 'unit' => 'liter', 'description' => 'Concentrated flavor solution'],
            ['name' => 'Salt', 'unit' => 'kg', 'description' => 'Table salt'],
            ['name' => 'Citric Acid', 'unit' => 'kg', 'description' => 'Citric acid used as acidity regulator'],
        ];

        foreach ($materials as $m) {
            DB::table('raw_materials')->updateOrInsert(
                ['name' => $m['name']],
                ['unit' => $m['unit'], 'description' => $m['description']]
            );
        }

        $this->command->info('✅ تم إدخال المواد الأولية الافتراضية');
    }
}
