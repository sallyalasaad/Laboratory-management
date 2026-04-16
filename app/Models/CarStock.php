<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CarStock extends Model
{
    protected $fillable = ['user_id','distribution_task_id','status'];

    public function items()
    {
        return $this->hasMany(CarStockItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
