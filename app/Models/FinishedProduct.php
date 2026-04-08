<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinishedProduct extends Model
{
    use HasFactory;

    protected $fillable = ['name','size','unit','description'];

    public function batches()
    {
        return $this->hasMany(FinishedProductBatch::class);
    }


}
