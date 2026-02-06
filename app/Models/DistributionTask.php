<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class DistributionTask extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','status','route','date'];

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
}
