<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinishedProduct;

class FinishedProductsSeeder extends Seeder
{
    public function run()
    {
        $products = [
            ['name'=>'جبنة','size'=>350,'unit'=>'غرام'],
            ['name'=>'جبنة','size'=>1,'unit'=>'كيلو'],
            ['name'=>'جبنة','size'=>3,'unit'=>'كيلو'],
            ['name'=>'جبنة','size'=>5,'unit'=>'كيلو'],
            ['name'=>'لبنة','size'=>1,'unit'=>'كيلو'],
            ['name'=>'لبنة','size'=>3,'unit'=>'كيلو'],
        ];

        foreach($products as $product){
            FinishedProduct::updateOrCreate(
                ['name' => $product['name'], 'size' => $product['size']], // تمييز حسب الاسم والحجم
                $product
            );
        }
    }
}
