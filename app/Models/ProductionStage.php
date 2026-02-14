<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionStage extends Model
{
    use HasFactory;
    protected $fillable = [
        'production_order_id','stage_name','status','start_date','end_date'
    ];

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }


}
