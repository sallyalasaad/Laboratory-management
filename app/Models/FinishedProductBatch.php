<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class FinishedProductBatch extends Model
{
    use HasFactory;

    protected $fillable = ['finished_product_id','production_order_id','batch_number','quantity','remaining_quantity','production_date','expiry_date','status'];

    protected static function booted()
    {
        static::creating(function (FinishedProductBatch $batch) {
            if ($batch->remaining_quantity === null && isset($batch->quantity)) {
                $batch->remaining_quantity = $batch->quantity;
            }
        });
    }

    public function finishedProduct()
    {
        return $this->belongsTo(FinishedProduct::class);
    }

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function distributionTasks()
    {
        return $this->belongsToMany(
            DistributionTask::class,
            'distribution_items'
        )->withPivot('quantity');
    }

    /*public function sales()
    {
        return $this->belongsToMany(
            Sale::class,
            'sale_items'
        )->withPivot('quantity','price');
    }*/

    public function waste()
    {
        return $this->hasMany(Waste::class);
    }
}


