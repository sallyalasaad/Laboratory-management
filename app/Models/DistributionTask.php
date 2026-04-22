<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class DistributionTask extends Model
{
    use HasFactory;


    protected $fillable = [
        'user_id',
        'region_id',
        'date',
        'start_time',
        'end_time',
        'status'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function batches()
    {
        return $this->belongsToMany(
            FinishedProductBatch::class,
            'distribution_items'
        )->withPivot('quantity');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }



    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function stores()
    {
        return $this->belongsToMany(
            Store::class,
            'task_stores'
        )->withPivot('visited','visited_at');
    }


}
