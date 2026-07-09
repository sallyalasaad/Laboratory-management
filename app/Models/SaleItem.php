<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'car_stock_item_id',
        'finished_product_id',
        'finished_product_batch_id',
        'quantity',
        'price'
    ];


    public function carStockItem()
    {
        return $this->belongsTo(CarStockItem::class);
    }
    
}
