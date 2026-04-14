<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = ['store_id','distribution_task_id','date','total_amount',    'status',
    ];

    public function distributionTask()
    {
        return $this->belongsTo(DistributionTask::class);
    }

    public function batches()
    {
        return $this->belongsToMany(
            FinishedProductBatch::class,
            'sale_items'
        )->withPivot('quantity','price');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

}
