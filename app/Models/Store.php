<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{protected $fillable = [
    'region_id',
    'name',
    'barcode',
    'lat',
    'lng'
];
    use HasFactory;
    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function tasks()
    {
        return $this->belongsToMany(
            DistributionTask::class,
            'task_stores'
        )->withPivot('visited','visited_at');
    }
}
