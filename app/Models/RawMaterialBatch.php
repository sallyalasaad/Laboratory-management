<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class RawMaterialBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_id','batch_number','quantity','expiry_date'
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

    public function waste()
    {
        return $this->hasMany(Waste::class);
    }
}
