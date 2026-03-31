<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ProductionOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_number',
        'user_id',
        'finished_product_id',
        'quantity',
        'status',
        'start_date',
        'end_date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stages()
    {
        return $this->hasMany(ProductionStage::class);
    }

    public function rawMaterialBatches()
    {
        return $this->belongsToMany(
            RawMaterialBatch::class,
            'production_materials'
        )->withPivot('quantity');
    }

    public function finishedProductBatches()
    {
        return $this->hasMany(FinishedProductBatch::class);
    }
    public function product()
    {
        return $this->belongsTo(FinishedProduct::class,'finished_product_id');
    }
    public function notes()
    {
        return $this->hasMany(Note::class, 'production_order_id');
    }
}
