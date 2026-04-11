<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['name'];
    use HasFactory;
    public function stores()
    {
        return $this->hasMany(Store::class);
    }
}
