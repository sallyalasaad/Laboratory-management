<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
   public function run(): void
{
    Store::insert([

        // =========================
        // كفرسوسة - Region 1
        // =========================

        [
            'region_id' => 1,
            'name' => 'Cham City Center',
            'barcode' => 'WHO001',
            'lat' => 33.50069,
            'lng' => 36.27435,
            'type' => 'wholesale'
        ],
        [
    'region_id' => 1, // كفرسوسة
    'name' => 'سوبر ماركت العائلة',
    'barcode' => 'RET010',
    'lat' => 33.50089,
    'lng' => 36.27828,
    'type' => 'retail'
        ],
        [
    'region_id' => 1, // دمشق / كفرسوسة وما حولها
    'name' => 'Target Market',
    'barcode' => 'RET013',
    'lat' => 33.50050,
    'lng' => 36.27490,
    'type' => 'retail'
],

        // =========================
        // برزة - Region 2
        // =========================

        [
            'region_id' => 2,
            'name' => 'Qasioun Mall',
            'barcode' => 'WHO002',
            'lat' => 33.54890,
            'lng' => 36.31381,
            'type' => 'wholesale'
        ],


        [
            'region_id' => 2,
            'name' => 'Barzeh Bakery 2',
            'barcode' => 'RET001',
            'lat' => 33.55167,
            'lng' => 36.31554,
            'type' => 'retail'
        ],
        [
    'region_id' => 2, // برزة
    'name' => 'Super omelets Supermarket',
    'barcode' => 'RET011',
    'lat' => 33.55200,
    'lng' => 36.31500,
    'type' => 'retail'
                ],
[
    'region_id' => 2, // برزة
    'name' => 'سوبرماركت العزيز',
    'barcode' => 'RET012',
    'lat' => 33.54300,
    'lng' => 36.32200,
    'type' => 'retail'
],
        // =========================
        // المالكي - Region 3
        // =========================

        [
            'region_id' => 3,
            'name' => 'Malki Market',
            'barcode' => 'RET002',
            'lat' => 33.51988,
            'lng' => 36.27716,
            'type' => 'retail'
        ],


        // =========================
        // صحنايا - Region 4
        // =========================

        [
            'region_id' => 4,
            'name' => 'Town Center',
            'barcode' => 'WHO003',
            'lat' => 33.45788,
            'lng' => 36.27239,
            'type' => 'wholesale'
        ],


        // =========================
        // دمر - Region 5
        // =========================

        [
            'region_id' => 5,
            'name' => 'City Mart Mall Dummar',
            'barcode' => 'WHO004',
            'lat' => 33.53330,
            'lng' => 36.23330,
            'type' => 'wholesale'
        ],
        
    ]);
}
}
