<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Waste extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_batch_id',
        'finished_product_batch_id',
        'quantity','reason'
    ];


}
