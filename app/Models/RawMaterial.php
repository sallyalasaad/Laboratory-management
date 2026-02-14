<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasFactory;
    protected $fillable = ['name','unit','description'];

    public function batches()
    {
        return $this->hasMany(RawMaterialBatch::class);
    }


}
