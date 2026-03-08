<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinishedProduct;

class FinishedProductsSeeder extends Seeder
{
    public function run()
    {
        $products = [
            ['name'=>'جبنة','unit'=>'غرام','description'=>'350 غرام'],
            ['name'=>'جبنة','unit'=>'كيلو','description'=>'1 كيلو'],
            ['name'=>'جبنة','unit'=>'كيلو','description'=>'3 كيلو'],
            ['name'=>'جبنة','unit'=>'كيلو','description'=>'5 كيلو'],
            ['name'=>'لبنة','unit'=>'كيلو','description'=>'1 كيلو'],
            ['name'=>'لبنة','unit'=>'كيلو','description'=>'3 كيلو'],
        ];

        foreach($products as $product){
            FinishedProduct::updateOrCreate(
                [
                    'name' => $product['name'],
                    'description' => $product['description'], // نميز حسب الاسم والوصف
                ],
                $product
            );
        }
    }
}
