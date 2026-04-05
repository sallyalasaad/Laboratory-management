<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class RawMaterialBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_id','batch_number','quantity','expiry_date','received_at','remaining_quantity'
    ];
       protected $casts = [
        'received_at' => 'datetime:Y-m-d H:i:s',
        //'expiry_date' => 'date:Y-m-d'
        ];

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function productionOrders()
    {
        return $this->belongsToMany(
            ProductionOrder::class,
            'production_materials'
        )->withPivot('quantity');
    }

    public function scopeAvailable($query)
    {
        return $query->where('remaining_quantity', '>', 0)->orderBy('received_at', 'asc');
    }

    public function waste()
    {
        return $this->hasMany(Waste::class);
    }
}
