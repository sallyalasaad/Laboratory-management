<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CarStockItem extends Model
{
    protected $fillable = [
        'car_stock_id',
        'finished_product_id',
        'finished_product_batch_id',
        'quantity',
        'remaining_quantity'
    ];
    public function finishedProductBatch()
{
    return $this->belongsTo(
        FinishedProductBatch::class,
        'finished_product_batch_id'
    );
}

    public function carStock()
    {
        return $this->belongsTo(CarStock::class);
    }

    public function batch()
    {
        return $this->belongsTo(FinishedProductBatch::class,'finished_product_batch_id');
    }
}
